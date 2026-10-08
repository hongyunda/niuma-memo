<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { projectApi } from '@/shared/api'
import type { Project, ResourceCategory } from '@/shared/api/types'
import { useAppStore } from '@/shared/stores/app'
import { feedback } from '@/shared/ui/feedback'
import PageHeader from '@/mobile/components/PageHeader.vue'
import NoteList from '@/mobile/components/NoteList.vue'
import ProjectForm from '@/mobile/components/ProjectForm.vue'
import ProjectResourcesModal from '@/shared/components/ProjectResourcesModal.vue'
const route = useRoute()
const router = useRouter()
const app = useAppStore()
const id = computed(() => Number(route.params.id))
const project = ref<Project | null>(null)
const showForm = ref(false)
const showMenu = ref(false)
const showResources = ref(false)
const category = ref<ResourceCategory>('files')
const categories: { value: ResourceCategory; label: string }[] = [
  { value: 'files', label: '文件' }, { value: 'image', label: '图片' }, { value: 'video', label: '视频' }, { value: 'link', label: '链接' },
  { value: 'table', label: '表格' }, { value: 'pdf', label: 'PDF' }, { value: 'other', label: '其他文件' },
]
const menu = computed(() => [
  { name: '编辑项目', key: 'edit' },
  { name: project.value?.status === 2 ? '取消归档' : '归档项目', key: 'archive' },
  { name: '删除项目', key: 'delete', color: '#ef4444' },
])
async function load() { const current = id.value; const p = await projectApi.get(current); if (current === id.value) project.value = p }
async function onMenu(a: { key: string }) {
  const p = project.value
  if (!p) return
  if (a.key === 'edit') showForm.value = true
  else if (a.key === 'archive') { await projectApi.update(p.id, { status: p.status === 2 ? 1 : 2 }); load(); app.fetchProjects() }
  else if (a.key === 'delete') {
    if (!(await feedback.confirm({ title: `删除「${p.name}」`, message: p.note_count ? '记录会保留，可从最近编辑和搜索打开。' : '确定删除该项目？', danger: true, confirmText: '删除' }))) return
    await projectApi.remove(p.id); app.fetchProjects(); router.replace('/projects')
  }
}
function newNote() { router.push({ name: 'note-new', query: { project_id: id.value } }) }
watch(id, () => { showResources.value = false; load() })
onMounted(() => { load(); window.addEventListener('note:created', load) })
onBeforeUnmount(() => window.removeEventListener('note:created', load))
</script>
<template>
  <div class="page">
    <PageHeader :title="project?.name || '项目'" fallback="/projects"><template #right><van-icon name="plus" class="mr-3" size="20" aria-label="新建记录" @click="newNote" /><van-icon name="ellipsis" size="20" @click="showMenu = true" /></template></PageHeader>
    <div v-if="project?.description" class="project-description">{{ project.description }}</div>
    <nav class="project-resources" aria-label="项目资源"><button v-for="c in categories" :key="c.value" @click="category = c.value; showResources = true">{{ c.label }}</button></nav>
    <NoteList :query="{ project_id: id }" empty-text="这个项目还没有记录" />
    <ProjectForm v-model:show="showForm" :project="project" @saved="load(); app.fetchProjects()" />
    <van-action-sheet v-model:show="showMenu" :actions="menu" cancel-text="取消" close-on-click-action @select="onMenu" />
    <ProjectResourcesModal v-model:show="showResources" :project-id="id" :project-name="project?.name || '项目'" :category="category" @open-note="noteId => { showResources = false; router.push(`/notes/${noteId}`) }" />
  </div>
</template>
<style scoped>
.project-description { padding: 16px; font-size: 14px; color: var(--t-2); white-space: pre-wrap; }
.project-resources { display: flex; gap: 4px; padding: 10px 12px; overflow-x: auto; background: #fff; border-bottom: 1px solid var(--b-1); }
.project-resources button { flex: 0 0 auto; padding: 7px 10px; border: 0; background: transparent; color: var(--t-2); font-size: 13px; }
</style>
