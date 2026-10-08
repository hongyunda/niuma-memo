<script setup lang="ts">
import { computed, h } from 'vue'
import type { NoteListItem } from '@/shared/api/types'
import { dayjs, smartTime } from '@/shared/utils/format'
import { useAppStore } from '@/shared/stores/app'

const props = defineProps<{ note: NoteListItem; selected?: boolean; showProject?: boolean; compact?: boolean; hidePreview?: boolean }>()
const emit = defineEmits<{ (e: 'select', n: NoteListItem): void; (e: 'action', key: string, n: NoteListItem): void }>()
const app = useAppStore()
const overdue = computed(() => props.note.next_reminder && dayjs(props.note.next_reminder.remind_at).isBefore(dayjs()))
const icon = (cls: string) => () => h('i', { class: cls })
const actions = computed(() => [
  { label: props.note.is_pinned ? '取消置顶' : '置顶', key: 'pin', icon: icon('i-tabler-pin') },
  { label: '移动到项目', key: 'move', icon: icon('i-tabler-arrows-exchange') },
  { label: props.note.is_archived ? '取消归档' : '归档', key: 'archive', icon: icon('i-tabler-archive') },
  { type: 'divider', key: 'd' },
  { label: '删除', key: 'delete', icon: icon('i-tabler-trash'), props: { style: 'color:#ef4444' } },
])

function onDragStart(e: DragEvent) {
  e.dataTransfer?.setData('text/note-id', String(props.note.id))
  e.dataTransfer?.setData('text/plain', props.note.title)
  if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
}
</script>

<template>
  <div class="row" :class="{ selected, compact }" :data-id="note.id" draggable="true" @dragstart="onDragStart" @click="emit('select', note)">
    <div class="body">
      <div class="l1">
        <i v-if="note.is_pinned" class="i-tabler-pin-filled pin" />
        <span class="title">{{ note.title || '（无标题）' }}</span>
        <span class="time">{{ smartTime(note.updated_at) }}</span>
      </div>
      <div v-if="!compact && !hidePreview && note.summary && note.summary !== note.title" class="snippet">{{ note.summary }}</div>
      <div v-if="note.tags.length || note.attachment_count || note.has_audio || note.next_reminder || showProject" class="l3">
        <span v-for="t in note.tags.slice(0, 3)" :key="t.id" class="tag">#{{ t.name }}</span>
        <span v-if="note.attachment_count" class="m"><i class="i-tabler-paperclip" />{{ note.attachment_count }}</span>
        <span v-if="note.has_audio" class="m"><i class="i-tabler-microphone" /></span>
        <span v-if="note.next_reminder" class="m" :class="{ red: overdue }"><i class="i-tabler-bell" />{{ smartTime(note.next_reminder.remind_at) }}</span>
        <span v-if="showProject" class="m proj">{{ app.projectName(note.project_id) }}</span>
      </div>
      <div v-if="!compact && !hidePreview && note.images.length" class="thumbs"><img v-for="(img, i) in note.images.slice(0, 4)" :key="i" :src="img" loading="lazy" /></div>
    </div>
    <n-dropdown :options="actions" trigger="click" placement="bottom-end" @select="(k: string) => emit('action', k, note)">
      <button class="more" title="更多" @click.stop><i class="i-tabler-dots" /></button>
    </n-dropdown>
  </div>
</template>

<style scoped>
.row { position: relative; display: flex; gap: 10px; padding: 10px 14px 10px 16px; border-bottom: 1px solid var(--b-2); cursor: pointer; background: #fff; transition: background var(--dur); }
.row.compact { padding: 8px 14px 8px 12px; }
.row:hover { background: var(--bg-hover); }
.row.selected { background: var(--bg-active); }
.body { flex: 1; min-width: 0; }
.l1 { display: flex; align-items: center; gap: 5px; }
.pin { color: var(--t-3); font-size: 13px; }
.title { flex: 1; min-width: 0; font-size: 14.5px; font-weight: 600; color: var(--t-1); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.compact .title { font-size: 14px; font-weight: 500; }
.time { font-size: 11px; color: var(--t-3); flex: 0 0 auto; }
.snippet { margin-top: 3px; font-size: 12.5px; line-height: 1.5; color: var(--t-2); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.l3 { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 8px; margin-top: 5px; font-size: 11.5px; color: var(--t-3); }
.compact .l3 { margin-top: 3px; }
.tag { color: var(--t-3); }
.m { display: inline-flex; align-items: center; gap: 2px; }
.m i { font-size: 13px; }
.m.red { color: var(--c-danger); }
.proj { margin-left: auto; }
.thumbs { display: flex; gap: 4px; margin-top: 6px; }
.thumbs img { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; background: var(--bg-muted); }
.more { position: absolute; right: 8px; top: 8px; width: 26px; height: 26px; border: 0; border-radius: 6px; background: #fff; color: var(--t-2); cursor: pointer; opacity: 0; transition: opacity var(--dur); box-shadow: 0 1px 3px rgba(0,0,0,0.12); display: inline-flex; align-items: center; justify-content: center; font-size: 16px; }
.row:hover .more, .row.selected .more { opacity: 1; }
.more:hover { background: var(--bg-muted); }
</style>
