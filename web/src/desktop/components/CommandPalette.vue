<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import { searchApi } from '@/shared/api'
import type { Attachment, NoteListItem } from '@/shared/api/types'
import { useAppStore } from '@/shared/stores/app'
import { FILE_TYPES, smartTime } from '@/shared/utils/format'

/**
 * 命令面板（⌘K）：搜记录 / 文件，也能直接跳页面、新建
 */
const props = defineProps<{ show: boolean }>()
const emit = defineEmits<{ (e: 'update:show', v: boolean): void }>()
const router = useRouter()
const app = useAppStore()
const q = ref('')
const notes = ref<NoteListItem[]>([])
const files = ref<Attachment[]>([])
const loading = ref(false)
const active = ref(0)
const inputRef = ref<HTMLInputElement>()

type Item = { kind: 'action' | 'note' | 'file' | 'project'; label: string; sub?: string; icon: string; color?: string; run: () => void }

const actions = computed<Item[]>(() => {
  const kw = q.value.trim()
  const base: Item[] = [
    { kind: 'action', label: '新建记录', icon: 'i-tabler-plus', run: () => router.push('/notes/new') },
    { kind: 'action', label: '打开工作台', icon: 'i-tabler-layout-dashboard', run: () => router.push('/') },
    { kind: 'action', label: '打开提醒', icon: 'i-tabler-bell', run: () => router.push('/reminders') },
    { kind: 'action', label: '设置与推送', icon: 'i-tabler-settings', run: () => router.push('/settings') },
  ]
  const projects: Item[] = app.activeProjects.map((p) => ({ kind: 'project', label: p.name, icon: 'i-tabler-folder', run: () => router.push(`/projects/${p.id}`) }))
  const all = [...projects, ...base]
  return kw ? all.filter((a) => a.label.includes(kw) || a.sub?.includes(kw)) : all.slice(0, 8)
})

const items = computed<Item[]>(() => [
  ...actions.value,
  ...notes.value.map<Item>((n) => ({ kind: 'note', label: n.title || '（无标题）', sub: `${app.projectName(n.project_id)} · ${smartTime(n.updated_at)}`, icon: 'i-tabler-file-text', run: () => router.push(`/notes/${n.id}`) })),
  ...files.value.map<Item>((a) => ({ kind: 'file', label: a.original_name, sub: a.transcript ? a.transcript.slice(0, 60) : a.note?.title, icon: 'i-tabler-paperclip', color: FILE_TYPES[a.file_type].color, run: () => (a.note_id ? router.push(`/notes/${a.note_id}`) : window.open(a.url, '_blank')) })),
])

const search = useDebounceFn(async () => {
  const kw = q.value.trim()
  if (!kw) {
    notes.value = []
    files.value = []
    return
  }
  loading.value = true
  try {
    const res = await searchApi.query(kw)
    notes.value = res.notes.list.slice(0, 8)
    files.value = res.attachments.slice(0, 5)
  } finally {
    loading.value = false
  }
}, 180)

watch(q, () => {
  active.value = 0
  search()
})
watch(() => props.show, async (v) => {
  if (v) {
    q.value = ''
    notes.value = []
    files.value = []
    active.value = 0
    await nextTick()
    inputRef.value?.focus()
  }
})

function close() {
  emit('update:show', false)
}
function run(item: Item) {
  close()
  item.run()
}
function onKey(e: KeyboardEvent) {
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    active.value = Math.min(items.value.length - 1, active.value + 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    active.value = Math.max(0, active.value - 1)
  } else if (e.key === 'Enter') {
    const it = items.value[active.value]
    if (it) run(it)
    else if (q.value.trim()) {
      close()
      router.push({ name: 'search', query: { q: q.value.trim() } })
    }
  } else if (e.key === 'Escape') close()
}
</script>

<template>
  <n-modal :show="show" :mask-closable="true" :auto-focus="false" transform-origin="center" @update:show="emit('update:show', $event)">
    <div class="cp" @keydown="onKey">
      <div class="cp-input">
        <i class="i-tabler-search" />
        <input ref="inputRef" v-model="q" placeholder="搜记录、文件、语音内容，或输入命令…" />
        <span v-if="loading" class="cp-spin" />
        <kbd v-else>Esc</kbd>
      </div>
      <div class="cp-list">
        <template v-for="(group, gi) in [['project', '项目'], ['action', '操作'], ['note', '记录'], ['file', '文件']] as const" :key="gi">
          <template v-if="items.some((i) => i.kind === group[0])">
            <div class="cp-group">{{ group[1] }}</div>
            <div
              v-for="it in items.filter((i) => i.kind === group[0])"
              :key="it.kind + it.label + (it.sub || '')"
              class="cp-item"
              :class="{ active: items.indexOf(it) === active }"
              @mouseenter="active = items.indexOf(it)"
              @click="run(it)"
            >
              <i :class="it.icon" style="color: var(--t-3)" />
              <div class="min-w-0 flex-1">
                <div class="cp-label text-ellipsis">{{ it.label }}</div>
                <div v-if="it.sub" class="cp-sub text-ellipsis">{{ it.sub }}</div>
              </div>
              <kbd v-if="items.indexOf(it) === active">↵</kbd>
            </div>
          </template>
        </template>
        <div v-if="q.trim() && !loading && !notes.length && !files.length && !actions.length" class="cp-empty">没有匹配的内容，回车查看完整搜索结果</div>
      </div>
      <div class="cp-foot"><span><kbd>↑</kbd><kbd>↓</kbd> 选择</span><span><kbd>↵</kbd> 打开</span><span><kbd>Esc</kbd> 关闭</span></div>
    </div>
  </n-modal>
</template>

<style scoped>
.cp { width: min(640px, calc(100vw - 48px)); background: #fff; border-radius: 16px; box-shadow: 0 24px 80px rgba(31, 35, 41, 0.28); overflow: hidden; margin-top: -12vh; }
.cp-input { display: flex; align-items: center; gap: 10px; padding: 14px 18px; border-bottom: 1px solid var(--b-2); }
.cp-input i { font-size: 20px; color: var(--t-3); }
.cp-input input { flex: 1; border: 0; outline: none; font-size: 16px; font-family: inherit; color: var(--t-1); background: transparent; }
.cp-input kbd, .cp-foot kbd, .cp-item kbd { font-family: inherit; font-size: 11px; color: var(--t-3); background: var(--bg-muted); border-radius: 4px; padding: 1px 6px; }
.cp-spin { width: 16px; height: 16px; border: 2px solid var(--b-1); border-top-color: var(--c-primary); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.cp-list { max-height: 56vh; overflow-y: auto; padding: 6px; }
.cp-group { font-size: 11px; color: var(--t-3); padding: 8px 12px 4px; }
.cp-item { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 9px; cursor: pointer; }
.cp-item i { font-size: 18px; }
.cp-item.active { background: var(--c-primary-50); }
.cp-label { font-size: 14px; color: var(--t-1); }
.cp-sub { font-size: 12px; color: var(--t-3); }
.cp-empty { padding: 28px; text-align: center; font-size: 13px; color: var(--t-3); }
.cp-foot { display: flex; gap: 16px; padding: 10px 18px; border-top: 1px solid var(--b-2); font-size: 12px; color: var(--t-3); background: #fafbfc; }
</style>
