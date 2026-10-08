<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { NoteListItem, NoteQuery } from '@/shared/api/types'
import { useNoteList } from '@/shared/composables/useNoteList'
import NoteRow from './NoteRow.vue'
import MoveDialog from '@/desktop/dialogs/MoveDialog.vue'

const props = defineProps<{ query: NoteQuery; selectedId: number | null; autoSelect?: boolean; showProject?: boolean; emptyText?: string }>()
const emit = defineEmits<{ (e: 'select', id: number | null): void; (e: 'loaded', total: number): void; (e: 'changed'): void }>()

const { list, loading, finished, error, total, empty, load, reload, remove, patch, togglePin, toggleArchive, destroy, moveTo } = useNoteList(() => props.query, 30)
const bodyRef = ref<HTMLElement>()
const moveTarget = ref<NoteListItem | null>(null)
const showMove = ref(false)

watch([loading, error, total], () => {
  if (!loading.value && !error.value) emit('loaded', total.value)
}, { immediate: true })
watch([loading, error, () => props.autoSelect, () => list.value[0]?.id], () => {
  if (props.autoSelect && !loading.value && !error.value && list.value[0]) emit('select', list.value[0].id)
}, { immediate: true, flush: 'post' })

function onScroll() {
  const el = bodyRef.value
  if (!el || finished.value || loading.value) return
  if (el.scrollTop + el.clientHeight >= el.scrollHeight - 200) load()
}

function moveSelection(delta: number) {
  if (!list.value.length) return
  const idx = list.value.findIndex((n) => n.id === props.selectedId)
  const target = list.value[Math.min(list.value.length - 1, Math.max(0, idx + delta))]
  if (target) {
    emit('select', target.id)
    bodyRef.value?.querySelector(`[data-id="${target.id}"]`)?.scrollIntoView({ block: 'nearest' })
  }
}

async function onAction(key: string, n: NoteListItem) {
  if (key === 'pin') await togglePin(n)
  else if (key === 'archive') {
    await toggleArchive(n)
    if (props.selectedId === n.id) emit('select', null)
  } else if (key === 'move') {
    moveTarget.value = n
    showMove.value = true
    return
  } else if (key === 'delete') {
    const ok = await destroy(n)
    if (ok && props.selectedId === n.id) emit('select', null)
  }
  emit('changed')
}

async function doMove(pid: number | null) {
  if (!moveTarget.value) return
  await moveTo(moveTarget.value, pid)
  if (props.selectedId === moveTarget.value.id) emit('select', null)
  moveTarget.value = null
  emit('changed')
}

const onExternal = () => reload()
onMounted(() => {
  window.addEventListener('note:created', onExternal)
  window.addEventListener('note:moved', onExternal)
})
onBeforeUnmount(() => {
  window.removeEventListener('note:created', onExternal)
  window.removeEventListener('note:moved', onExternal)
})

defineExpose({ reload, moveSelection, patch, remove, list })
</script>

<template>
  <div ref="bodyRef" class="lp" @scroll.passive="onScroll">
    <NoteRow v-for="n in list" :key="n.id" :note="n" :selected="n.id === selectedId" :show-project="showProject" hide-preview @select="emit('select', $event.id)" @action="onAction" />
    <div v-if="loading && !list.length" class="lp-loading"><n-spin size="small" /></div>
    <div v-else-if="empty" class="lp-empty">
      <i class="i-tabler-notes-off" />
      <div>{{ emptyText || '这里还没有记录' }}</div>
      <div class="hint">按 <kbd>N</kbd> 新建一条</div>
    </div>
    <div v-else-if="finished && list.length" class="lp-foot">共 {{ total }} 条</div>
    <MoveDialog v-model:show="showMove" :current="moveTarget?.project_id ?? null" @select="doMove" />
  </div>
</template>

<style scoped>
.lp { flex: 1; overflow-y: auto; min-height: 0; background: #fff; }
.lp-loading { padding: 40px; text-align: center; }
.lp-empty { padding: 64px 20px; text-align: center; color: var(--t-3); font-size: 13px; display: flex; flex-direction: column; align-items: center; gap: 6px; }
.lp-empty i { font-size: 40px; color: var(--t-4); }
.hint kbd { font-family: inherit; font-size: 11px; background: var(--bg-muted); border-radius: 4px; padding: 1px 6px; }
.lp-foot { padding: 12px; text-align: center; font-size: 12px; color: var(--t-3); }
</style>
