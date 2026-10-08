<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { projectApi } from '@/shared/api'
import type { ProjectResource, ResourceCategory } from '@/shared/api/types'
import { FILE_TYPES, fileSize, smartTime } from '@/shared/utils/format'

const props = defineProps<{ show: boolean; projectId: number; projectName: string; category: ResourceCategory }>()
const emit = defineEmits<{ (e: 'update:show', v: boolean): void; (e: 'open-note', id: number): void }>()
const dialog = ref<HTMLDialogElement>()
const searchInput = ref<HTMLInputElement>()
const category = ref<ResourceCategory>('files')
const keyword = ref('')
const items = ref<ProjectResource[]>([])
const selected = ref<ProjectResource | null>(null)
const loading = ref(false)
const error = ref(false)
const total = ref(0)
const page = ref(1)
const lastPage = ref(1)
let generation = 0
const categories: { value: ResourceCategory; label: string }[] = [
  { value: 'files', label: '文件' }, { value: 'image', label: '图片' }, { value: 'video', label: '视频' }, { value: 'link', label: '链接' },
  { value: 'table', label: '表格' }, { value: 'pdf', label: 'PDF' }, { value: 'other', label: '其他文件' },
]
const label = computed(() => categories.find(c => c.value === category.value)?.label || '文件')
const attachment = computed(() => selected.value?.attachment)
function close() { emit('update:show', false) }
async function load(reset = true) {
  if (!props.show || (!reset && loading.value)) return
  const request = reset ? ++generation : generation
  if (reset) { page.value = 1; items.value = []; selected.value = null; total.value = 0 }
  loading.value = true
  error.value = false
  try {
    const res = await projectApi.resources(props.projectId, { category: category.value, keyword: keyword.value.trim(), page: page.value, limit: 50 })
    if (request !== generation || !props.show) return
    items.value = reset ? res.list : [...items.value, ...res.list]
    total.value = res.total
    lastPage.value = res.last_page
    page.value = res.page + 1
    if (!selected.value) selected.value = items.value[0] || null
  } catch { if (request === generation) error.value = true }
  finally { if (request === generation) loading.value = false }
}
const onSearch = useDebounceFn(() => load(), 200)
function changeCategory(value: ResourceCategory) { if (category.value !== value) { category.value = value; load() } }
watch(() => props.show, async show => {
  await nextTick()
  if (show) {
    category.value = props.category
    keyword.value = ''
    if (!dialog.value?.open) dialog.value?.showModal()
    searchInput.value?.focus()
    load()
  } else { generation++; loading.value = false; dialog.value?.close() }
}, { immediate: true })
onBeforeUnmount(() => { generation++; dialog.value?.close() })
</script>

<template>
  <Teleport to="body">
    <dialog ref="dialog" class="pr-modal" aria-labelledby="resource-heading" @cancel.prevent="close">
      <div class="pr-shell">
        <header class="pr-header">
          <h1 id="resource-heading">{{ projectName }} · {{ label }}</h1>
          <button type="button" class="pr-close" aria-label="关闭项目资源" @click="close"><i class="i-tabler-x" /></button>
        </header>
        <div class="pr-tools">
          <nav class="pr-tabs" aria-label="资源分类">
            <button v-for="c in categories" :key="c.value" type="button" :class="{ active: category === c.value }" :aria-pressed="category === c.value" @click="changeCategory(c.value)">{{ c.label }}</button>
          </nav>
          <form class="pr-search" role="search" @submit.prevent="load()">
            <i class="i-tabler-search" />
            <input ref="searchInput" v-model="keyword" aria-label="搜索项目资源" placeholder="搜索名称、记录内容、链接…" @input="onSearch" />
            <button v-if="keyword" type="button" aria-label="清空搜索" @click="keyword = ''; load()"><i class="i-tabler-x" /></button>
          </form>
        </div>
        <div class="pr-content">
          <aside class="pr-list">
            <div class="pr-count">{{ total }} 项</div>
            <button v-for="item in items" :key="item.id" type="button" class="pr-item" :class="{ selected: selected?.id === item.id }" @click="selected = item">
              <img v-if="item.attachment?.file_type === 'image'" class="pr-thumb" :src="item.attachment.thumb_url || item.url" alt="" loading="lazy" />
              <i v-else :class="item.kind === 'link' ? 'i-tabler-link' : item.kind === 'table' || item.attachment?.file_type === 'excel' ? 'i-tabler-table' : item.attachment?.file_type === 'video' ? 'i-tabler-video' : 'i-tabler-file'" />
              <span class="pr-item-body"><strong>{{ item.title }}</strong><span>{{ item.note_title || smartTime(item.updated_at) }}</span></span>
            </button>
            <p v-if="loading" class="pr-state" role="status">加载中…</p>
            <div v-else-if="error" class="pr-state">加载失败 <button type="button" @click="load()">重试</button></div>
            <p v-else-if="!items.length" class="pr-state">{{ keyword.trim() ? '没有找到匹配的资源' : '当前项目暂无' + label }}</p>
            <button v-if="!loading && !error && page <= lastPage && items.length" class="pr-more" type="button" @click="load(false)">加载更多</button>
          </aside>
          <section class="pr-detail">
            <template v-if="selected">
              <div class="pr-info">
                <h2>{{ selected.title }}</h2>
                <div class="pr-meta"><span v-if="attachment">{{ FILE_TYPES[attachment.file_type].label }} · {{ fileSize(attachment.size) }}</span><span>{{ smartTime(selected.updated_at) }}</span></div>
                <div class="pr-actions">
                  <button v-if="selected.note_id" type="button" @click="emit('open-note', selected.note_id)"><i class="i-tabler-file-text" /> 查看所在记录</button>
                  <a v-if="selected.url" :href="selected.url" target="_blank" rel="noopener noreferrer"><i class="i-tabler-external-link" /> {{ selected.kind === 'link' ? '打开链接' : '打开文件' }}</a>
                  <a v-if="attachment" :href="attachment.url + '?download=1'"><i class="i-tabler-download" /> 下载</a>
                </div>
              </div>
              <div class="pr-preview">
                <img v-if="attachment?.file_type === 'image'" class="pr-image" :src="attachment.url" :alt="attachment.original_name" />
                <iframe v-else-if="attachment?.file_type === 'pdf'" class="pr-pdf" :src="attachment.url" :title="selected.title" />
                <audio v-else-if="attachment?.file_type === 'audio'" :src="attachment.url" controls preload="metadata" />
                <video v-else-if="attachment?.file_type === 'video'" :src="attachment.url" controls playsinline preload="metadata" />
                <div v-else-if="selected.kind === 'link'" class="pr-link"><i class="i-tabler-link" /><a :href="selected.url" target="_blank" rel="noopener noreferrer">{{ selected.url }}</a></div>
                <div v-else-if="selected.kind === 'table'" class="pr-table rich-content" v-html="selected.html" />
                <div v-else class="pr-file"><i class="i-tabler-file" /><p>{{ selected.title }}</p><a :href="selected.url" target="_blank" rel="noopener noreferrer">打开查看</a></div>
                <p v-if="attachment?.transcript" class="pr-transcript">{{ attachment.transcript }}</p>
              </div>
            </template>
            <p v-else class="pr-state">选择左侧资源查看详情</p>
          </section>
        </div>
      </div>
    </dialog>
  </Teleport>
</template>

<style scoped>
.pr-modal { width: 100vw; max-width: none; height: 100dvh; max-height: none; margin: 0; padding: 0; border: 0; background: #fff; color: var(--t-1); }
.pr-modal::backdrop { background: #fff; }
.pr-shell { height: 100%; display: flex; flex-direction: column; }
.pr-header { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 20px 32px; border-bottom: 1px solid var(--b-1); }
.pr-header h1 { min-width: 0; margin: 0; font-size: 21px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pr-close { display: grid; place-items: center; flex: 0 0 auto; width: 36px; height: 36px; border: 0; border-radius: 8px; background: transparent; color: var(--t-2); font-size: 23px; cursor: pointer; }
.pr-close:hover { background: var(--bg-muted); }
.pr-tools { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 16px 32px; border-bottom: 1px solid var(--b-2); }
.pr-tabs { display: flex; gap: 4px; overflow-x: auto; }
.pr-tabs button { flex: 0 0 auto; border: 0; padding: 8px 14px; background: transparent; color: var(--t-2); border-radius: 7px; font-size: 14px; cursor: pointer; }
.pr-tabs button.active { background: var(--bg-active); color: var(--c-primary); }
.pr-search { flex: 1; max-width: 600px; display: flex; align-items: center; gap: 10px; padding: 9px 12px; border: 1px solid var(--b-1); border-radius: 8px; }
.pr-search:focus-within { border-color: var(--c-primary); }
.pr-search input { flex: 1; min-width: 0; border: 0; outline: none; background: transparent; font: inherit; font-size: 14px; }
.pr-search button { border: 0; background: transparent; color: var(--t-2); cursor: pointer; }
.pr-content { flex: 1; min-height: 0; display: grid; grid-template-columns: minmax(260px, 30%) minmax(0, 1fr); }
.pr-list { overflow-y: auto; border-right: 1px solid var(--b-1); padding: 12px; }
.pr-count { color: var(--t-2); font-size: 12px; padding: 0 10px 10px; }
.pr-item { width: 100%; display: flex; align-items: center; gap: 12px; padding: 12px 10px; border: 0; border-radius: 8px; background: transparent; text-align: left; color: var(--t-1); cursor: pointer; }
.pr-item:hover { background: var(--bg-hover); }
.pr-item.selected { background: var(--bg-active); }
.pr-item > i { flex: 0 0 auto; font-size: 24px; color: var(--t-2); }
.pr-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; }
.pr-item-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 5px; }
.pr-item-body strong { font-size: 14px; font-weight: 500; overflow-wrap: anywhere; }
.pr-item-body > span { color: var(--t-2); font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pr-detail { min-width: 0; min-height: 0; display: flex; flex-direction: column; }
.pr-info { padding: 20px 28px; border-bottom: 1px solid var(--b-2); }
.pr-info h2 { margin: 0 0 8px; font-size: 18px; font-weight: 600; overflow-wrap: anywhere; }
.pr-meta { display: flex; gap: 16px; color: var(--t-2); font-size: 12px; }
.pr-actions { display: flex; flex-wrap: wrap; gap: 18px; margin-top: 14px; font-size: 13px; }
.pr-actions button { border: 0; padding: 0; background: transparent; color: var(--c-primary); cursor: pointer; font: inherit; }
.pr-actions i { vertical-align: -2px; margin-right: 3px; }
.pr-preview { flex: 1; min-height: 0; overflow: auto; padding: 24px 28px; background: #fafbfc; }
.pr-image { width: 100%; height: 100%; object-fit: contain; display: block; }
.pr-pdf { border: 0; width: 100%; height: 100%; min-height: 300px; background: #fff; }
.pr-preview video { width: 100%; max-height: 80%; }
.pr-preview audio { width: 100%; }
.pr-link { display: flex; align-items: flex-start; gap: 12px; overflow-wrap: anywhere; }
.pr-link i { font-size: 26px; flex: 0 0 auto; }
.pr-file { text-align: center; padding: 40px 16px; overflow-wrap: anywhere; }
.pr-file > i { font-size: 56px; color: var(--t-3); }
.pr-transcript { white-space: pre-wrap; line-height: 1.7; }
.pr-state { padding: 20px 12px; color: var(--t-2); font-size: 14px; }
.pr-state button, .pr-more { border: 0; background: transparent; color: var(--c-primary); cursor: pointer; padding: 8px; }
.pr-more { width: 100%; }
@media (max-width: 800px) {
  .pr-header { padding: 12px 16px; }
  .pr-header h1 { font-size: 18px; }
  .pr-tools { align-items: stretch; flex-direction: column; gap: 12px; padding: 12px; }
  .pr-tabs button { padding: 7px 10px; }
  .pr-search { max-width: none; }
  .pr-content { grid-template-columns: minmax(110px, 36%) minmax(0, 1fr); }
  .pr-list { padding: 8px 4px; }
  .pr-item { padding: 10px 6px; flex-wrap: wrap; gap: 6px; }
  .pr-item > i { font-size: 18px; }
  .pr-item-body { flex-basis: 100%; }
  .pr-item-body strong { font-size: 12px; }
  .pr-info { padding: 14px 12px; }
  .pr-info h2 { font-size: 15px; }
  .pr-meta, .pr-actions { gap: 8px; }
  .pr-preview { padding: 12px; }
}
</style>
