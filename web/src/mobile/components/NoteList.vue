<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { NoteListItem, NoteQuery } from '@/shared/api/types'
import { useNoteList } from '@/shared/composables/useNoteList'
import NoteCard from './NoteCard.vue'
import EmptyState from './EmptyState.vue'
import ProjectPicker from './ProjectPicker.vue'

const props = withDefaults(defineProps<{ query: NoteQuery; showProject?: boolean; emptyText?: string; pullRefresh?: boolean; selectable?: boolean; selected?: number[] }>(), {
  showProject: false,
  emptyText: '还没有记录',
  pullRefresh: true,
})
const emit = defineEmits<{ (e: 'loaded', total: number): void; (e: 'update:selected', ids: number[]): void }>()

const { list, loading, finished, error, total, load, reload, remove, togglePin, toggleArchive, destroy, moveTo } = useNoteList(() => props.query)
const refreshing = ref(false)
const moreTarget = ref<NoteListItem | null>(null)
const moveTarget = ref<NoteListItem | null>(null)
const showMove = ref(false)

watch(total, (t) => emit('loaded', t))

async function onRefresh() {
  await reload()
  refreshing.value = false
}

function toggleSelect(note: NoteListItem) {
  const set = new Set(props.selected || [])
  set.has(note.id) ? set.delete(note.id) : set.add(note.id)
  emit('update:selected', [...set])
}

const moreActions = computed(() => [
  { name: moreTarget.value?.is_pinned ? '取消置顶' : '置顶', key: 'pin' },
  { name: '移动到项目', key: 'move' },
  { name: moreTarget.value?.is_archived ? '取消归档' : '归档', key: 'archive' },
  { name: '删除', key: 'delete', color: '#ef4444' },
])

async function onMore(a: { key: string }) {
  const n = moreTarget.value
  moreTarget.value = null
  if (!n) return
  if (a.key === 'pin') togglePin(n)
  else if (a.key === 'archive') toggleArchive(n)
  else if (a.key === 'delete') destroy(n)
  else if (a.key === 'move') {
    moveTarget.value = n
    showMove.value = true
  }
}

defineExpose({ reload, list, total, removeIds: (ids: number[]) => ids.forEach(remove) })
</script>

<template>
  <van-pull-refresh v-model="refreshing" :disabled="!pullRefresh" @refresh="onRefresh">
    <van-list v-model:loading="loading" v-model:error="error" :finished="finished" :immediate-check="false" finished-text="" error-text="加载失败，点击重试" @load="load">
      <div class="note-list">
        <NoteCard
          v-for="n in list"
          :key="n.id"
          :note="n"
          :show-project="showProject"
          :selectable="selectable"
          :selected="selected?.includes(n.id)"
          swipe
          @archive="toggleArchive"
          @delete="destroy"
          @pin="togglePin"
          @select="toggleSelect"
          @more="moreTarget = $event"
        />
      </div>
      <EmptyState v-if="finished && !list.length && !loading" :description="emptyText" />
      <div v-else-if="finished && list.length" class="text-center text-xs text-gray-400 py-3">共 {{ total }} 条</div>
    </van-list>
    <van-action-sheet :show="!!moreTarget" :actions="moreActions" :description="moreTarget?.title" cancel-text="取消" close-on-click-action @select="onMore" @update:show="(v: boolean) => !v && (moreTarget = null)" />
    <ProjectPicker v-model:show="showMove" :model-value="moveTarget?.project_id ?? null" @update:model-value="(pid) => moveTarget && moveTo(moveTarget, pid)" />
  </van-pull-refresh>
</template>

<style scoped>
.note-list { display: flex; flex-direction: column; gap: 10px; padding: 10px 12px; }
.note-list :deep(.van-swipe-cell) { border-radius: var(--r-lg); overflow: hidden; box-shadow: var(--sh-1); }
</style>
