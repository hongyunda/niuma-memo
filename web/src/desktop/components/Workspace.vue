<script setup lang="ts">
import { computed, h, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router'
import { projectApi, searchApi } from '@/shared/api'
import type { Attachment, NoteDetail, NoteQuery, Project, ResourceCategory } from '@/shared/api/types'
import { useAppStore } from '@/shared/stores/app'
import { FILE_TYPES } from '@/shared/utils/format'
import { feedback } from '@/shared/ui/feedback'
import NoteListPane from './NoteListPane.vue'
import NoteEditorPane from './NoteEditorPane.vue'
import ProjectDialog from '@/desktop/dialogs/ProjectDialog.vue'
import ProjectResourcesModal from '@/shared/components/ProjectResourcesModal.vue'

const props = defineProps<{ mode: 'project' | 'search' | 'record' }>()
const route = useRoute()
const router = useRouter()
const app = useAppStore()
const project = ref<Project | null>(null)
const listTotal = ref<number | null>(null)
const listRef = ref<InstanceType<typeof NoteListPane>>()
const paneRef = ref<InstanceType<typeof NoteEditorPane>>()
const showProjectDialog = ref(false)
const searchInput = ref('')
const searchFiles = ref<Attachment[]>([])
const listWidth = ref(Number(localStorage.getItem('ws.listWidth')) || 400)
const projectId = computed(() => props.mode === 'project' ? Number(route.params.id) : null)
const q = computed(() => (route.query.q as string) || '')
const noteQ = computed(() => route.query.note as string | undefined || (route.name === 'note' ? String(route.params.id) : route.name === 'note-new' ? 'new' : undefined))
const selectedId = computed(() => noteQ.value && noteQ.value !== 'new' ? Number(noteQ.value) : null)
const listQuery = computed<NoteQuery>(() => props.mode === 'project' ? { project_id: projectId.value! } : props.mode === 'search' ? { keyword: q.value, archived: 'all' } : {})
const showEditor = computed(() => !!noteQ.value || (props.mode === 'project' && listTotal.value === 0))
let leaving = false
const resourceCategory = ref<ResourceCategory>('files')
const showResources = ref(false)
const categories: { value: ResourceCategory; label: string }[] = [
  { value: 'files', label: '文件' }, { value: 'image', label: '图片' }, { value: 'video', label: '视频' }, { value: 'link', label: '链接' },
  { value: 'table', label: '表格' }, { value: 'pdf', label: 'PDF' }, { value: 'other', label: '其他文件' },
]
function setQuery(patch: Record<string, string | number | undefined | null>) {
  const query = { ...route.query }
  for (const [k, v] of Object.entries(patch)) { if (v === undefined || v === null || v === '') delete query[k]; else query[k] = String(v) }
  delete query.tab; delete query.view; delete query.status; delete query.type
  return router.replace({ query })
}
function selectNote(id: number | null) {
  if (leaving) return
  if (props.mode === 'record') return router.push(id ? `/notes/${id}` : '/')
  return setQuery({ note: id ?? undefined })
}
function newNote() { props.mode === 'record' ? router.push('/notes/new') : setQuery({ note: 'new' }) }
async function closePane() { await paneRef.value?.flush(); selectNote(null) }
async function openResources(category: ResourceCategory) {
  await paneRef.value?.flush()
  resourceCategory.value = category
  showResources.value = true
}
async function loadProject() {
  if (!projectId.value) return
  const id = projectId.value
  try { const p = await projectApi.get(id); if (projectId.value === id) project.value = p }
  catch { if (projectId.value === id) router.replace('/projects') }
}
let projectTimer: number | undefined
const loadProjectSoon = () => { clearTimeout(projectTimer); projectTimer = window.setTimeout(loadProject, 800) }
function onCreated(n: NoteDetail) {
  if (leaving) return
  if (props.mode === 'record') router.replace(`/notes/${n.id}`)
  else setQuery({ note: n.id })
  listRef.value?.reload(); loadProject()
}
function onSaved(n: NoteDetail) {
  listRef.value?.patch(n.id, { title: n.title, is_pinned: n.is_pinned, is_archived: n.is_archived, updated_at: n.updated_at, project_id: n.project_id, attachment_count: n.attachments.length })
  loadProjectSoon()
}
function onDeleted(id: number) { listRef.value?.remove(id); selectNote(null); loadProject() }
const icon = (cls: string) => () => h('i', { class: cls })
const projectMenu = computed(() => [
  { label: '编辑项目', key: 'edit', icon: icon('i-tabler-edit') },
  { label: project.value?.status === 2 ? '取消归档' : '归档项目', key: 'archive', icon: icon('i-tabler-archive') },
  { type: 'divider', key: 'd' },
  { label: '删除项目', key: 'delete', icon: icon('i-tabler-trash'), props: { style: 'color:#ef4444' } },
])
async function onProjectMenu(key: string) {
  const p = project.value
  if (!p) return
  if (key === 'edit') showProjectDialog.value = true
  else if (key === 'archive') {
    await projectApi.update(p.id, { status: p.status === 2 ? 1 : 2 }); loadProject(); app.fetchProjects()
  } else if (key === 'delete') {
    if (!(await feedback.confirm({ title: `删除「${p.name}」`, message: p.note_count ? '记录会保留，可从最近编辑和搜索打开。' : '确定删除该项目？', danger: true, confirmText: '删除' }))) return
    await projectApi.remove(p.id); app.fetchProjects(); router.replace('/projects')
  }
}
function openSearchFile(a: Attachment) { a.note_id ? setQuery({ note: a.note_id }) : window.open(a.url, '_blank', 'noopener,noreferrer') }
function onKeydown(e: KeyboardEvent) {
  const el = document.activeElement as HTMLElement | null
  const typing = !!el && (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) || el.isContentEditable)
  if (showResources.value) return
  if (e.key === 'Escape') { if (noteQ.value && !typing) closePane(); return }
  if (typing || e.metaKey || e.ctrlKey || e.altKey) return
  if (e.key.toLowerCase() === 'n') { e.preventDefault(); newNote() }
  else if (e.key === 'ArrowDown' || e.key === 'j') { e.preventDefault(); listRef.value?.moveSelection(1) }
  else if (e.key === 'ArrowUp' || e.key === 'k') { e.preventDefault(); listRef.value?.moveSelection(-1) }
}
let stopResize: (() => void) | undefined
function startResize(e: MouseEvent) {
  const x = e.clientX, w = listWidth.value
  const move = (ev: MouseEvent) => { listWidth.value = Math.min(640, Math.max(300, w + ev.clientX - x)) }
  const up = () => { window.removeEventListener('mousemove', move); window.removeEventListener('mouseup', up); localStorage.setItem('ws.listWidth', String(listWidth.value)); document.body.style.cursor = ''; stopResize = undefined }
  stopResize = up; document.body.style.cursor = 'col-resize'
  window.addEventListener('mousemove', move); window.addEventListener('mouseup', up)
}
const onMoved = () => { listRef.value?.reload(); loadProject() }
async function flushBeforeLeave() {
  leaving = true
  try { await paneRef.value?.flush() }
  finally { leaving = false }
}
onBeforeRouteLeave(flushBeforeLeave)
onBeforeRouteUpdate(async (to, from) => {
  if (props.mode === 'project' && to.params.id !== from.params.id) await flushBeforeLeave()
})
watch(listQuery, () => { listTotal.value = null })
watch(projectId, () => { project.value = null; showResources.value = false; loadProject() })
watch(q, async () => {
  searchInput.value = q.value
  const keyword = q.value
  if (props.mode === 'search' && keyword) {
    const files = (await searchApi.query(keyword)).attachments
    if (keyword === q.value) searchFiles.value = files
  } else searchFiles.value = []
}, { immediate: true })
onMounted(() => { loadProject(); window.addEventListener('keydown', onKeydown); window.addEventListener('note:moved', onMoved) })
onBeforeUnmount(() => { clearTimeout(projectTimer); stopResize?.(); window.removeEventListener('keydown', onKeydown); window.removeEventListener('note:moved', onMoved) })
</script>

<template>
  <div class="ws" :style="{ '--list-w': listWidth + 'px' }">
    <header class="ws-bar">
      <div class="ws-title">
        <template v-if="mode === 'project'"><span class="dot" /><span class="name">{{ project?.name || '项目' }}</span></template>
        <template v-else-if="mode === 'search'">
          <i class="i-tabler-search" />
          <input v-model="searchInput" class="ws-search" aria-label="搜索记录和文件" placeholder="搜索记录、文件、链接…" @keydown.enter="setQuery({ q: searchInput.trim() || undefined, note: undefined })" />
        </template>
        <span v-else class="name">记录</span>
      </div>
      <nav v-if="mode === 'project'" class="ws-resources" aria-label="项目资源">
        <button v-for="c in categories" :key="c.value" :class="{ on: showResources && resourceCategory === c.value }" @click="openResources(c.value)">{{ c.label }}</button>
      </nav>
      <n-dropdown v-if="mode === 'project'" :options="projectMenu" trigger="click" @select="onProjectMenu">
        <n-button size="small" secondary circle aria-label="项目操作"><template #icon><i class="i-tabler-dots" /></template></n-button>
      </n-dropdown>
    </header>
    <aside class="ws-list">
      <div class="ws-list-head">
        <span>{{ mode === 'search' ? '搜索结果' : '记录' }} <span class="ws-list-count">{{ listTotal ?? 0 }}</span></span>
        <n-button v-if="mode !== 'search'" type="primary" size="small" @click="newNote"><template #icon><i class="i-tabler-plus" /></template>新建</n-button>
      </div>
      <NoteListPane ref="listRef" :query="listQuery" :selected-id="selectedId" :auto-select="!noteQ && (mode === 'project' || !!q)" :show-project="mode !== 'project'" :empty-text="mode === 'search' ? '没有找到相关记录' : '这个项目还没有记录'" @select="selectNote" @loaded="listTotal = $event" @changed="loadProjectSoon" />
      <div class="ws-resizer" @mousedown.prevent="startResize" />
    </aside>
    <main class="ws-detail">
      <NoteEditorPane v-if="showEditor" :key="projectId ?? undefined" ref="paneRef" :note-id="selectedId || 0" :initial-project-id="projectId" @created="onCreated" @saved="onSaved" @deleted="onDeleted" @close="closePane" />
      <div v-else-if="mode === 'project'" class="ws-loading"><n-spin size="small" /></div>
      <div v-else class="ws-blank">
        <template v-if="q && searchFiles.length">
          <h3>匹配的文件</h3>
          <div v-for="a in searchFiles" :key="a.id" class="ws-sfile" @click="openSearchFile(a)"><span>{{ FILE_TYPES[a.file_type].label }}</span><span>{{ a.original_name }}</span></div>
        </template>
        <template v-else><i class="i-tabler-search" /><p>{{ q ? '没有找到相关记录' : '输入关键词开始搜索' }}</p></template>
      </div>
    </main>
    <ProjectDialog v-model:show="showProjectDialog" :project="project" @saved="loadProject(); app.fetchProjects()" />
    <ProjectResourcesModal v-if="projectId" v-model:show="showResources" :project-id="projectId" :project-name="project?.name || '项目'" :category="resourceCategory" @open-note="id => { showResources = false; selectNote(id) }" />
  </div>
</template>

<style scoped>
.ws { display: grid; grid-template-rows: auto minmax(0, 1fr); grid-template-columns: var(--list-w) minmax(0, 1fr); height: 100vh; background: #fff; }
.ws-bar { grid-column: 1 / -1; display: flex; align-items: center; gap: 16px; padding: 10px 20px; border-bottom: 1px solid var(--b-1); min-height: 56px; }
.ws-title { display: flex; align-items: center; gap: 8px; min-width: 0; flex: 1; }
.ws-title .dot { background: var(--c-primary); }
.ws-title .name { font-size: 16px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ws-search { flex: 1; min-width: 0; border: 0; outline: none; font: inherit; font-size: 15px; color: var(--t-1); }
.ws-resources { display: flex; align-items: center; gap: 2px; overflow-x: auto; scrollbar-width: none; }
.ws-resources button { border: 0; border-radius: 6px; padding: 5px 10px; background: transparent; color: var(--t-2); font-size: 13px; cursor: pointer; white-space: nowrap; }
.ws-resources button:hover { background: var(--bg-muted); }
.ws-resources button.on { color: var(--c-primary); background: var(--bg-active); }
.ws-list { position: relative; border-right: 1px solid var(--b-1); display: flex; flex-direction: column; min-height: 0; }
.ws-list-head { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; border-bottom: 1px solid var(--b-2); font-size: 13px; color: var(--t-2); }
.ws-list-count { margin-left: 4px; }
.ws-resizer { position: absolute; top: 0; right: -3px; width: 6px; height: 100%; cursor: col-resize; z-index: 2; }
.ws-resizer:hover { background: var(--c-primary-100); }
.ws-detail { min-width: 0; min-height: 0; overflow-y: auto; }
.ws-loading { padding: 40px; text-align: center; }
.ws-blank { min-height: 60%; padding: 40px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; color: var(--t-2); text-align: center; }
.ws-blank h3, .ws-blank p { margin: 0; }
.ws-blank p { white-space: pre-wrap; }
.ws-blank > i { font-size: 36px; }
.ws-sfile { display: flex; gap: 12px; align-items: center; padding: 12px; cursor: pointer; }
</style>
