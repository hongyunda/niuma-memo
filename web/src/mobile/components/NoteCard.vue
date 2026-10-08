<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import type { NoteListItem } from '@/shared/api/types'
import { smartTime, duration } from '@/shared/utils/format'
import { useAppStore } from '@/shared/stores/app'

const props = defineProps<{ note: NoteListItem; showProject?: boolean; swipe?: boolean; selectable?: boolean; selected?: boolean }>()
const emit = defineEmits<{ (e: 'archive', note: NoteListItem): void; (e: 'delete', note: NoteListItem): void; (e: 'pin', note: NoteListItem): void; (e: 'select', note: NoteListItem): void; (e: 'more', note: NoteListItem): void }>()

// 长按 450ms 弹出操作面板（手机没有悬停）
let pressTimer: number | undefined
let pressed = false
function onTouchStart() {
  pressed = false
  pressTimer = window.setTimeout(() => {
    pressed = true
    navigator.vibrate?.(10)
    emit('more', props.note)
  }, 450)
}
function onTouchEnd() {
  clearTimeout(pressTimer)
}
const router = useRouter()
const app = useAppStore()
const overdue = computed(() => props.note.next_reminder && new Date(props.note.next_reminder.remind_at.replace(' ', 'T')) < new Date())

function open() {
  if (pressed) {
    pressed = false
    return
  }
  if (props.selectable) return emit('select', props.note)
  router.push(`/notes/${props.note.id}`)
}
</script>

<template>
  <van-swipe-cell :disabled="!swipe || selectable">
    <div
      class="note-card"
      :class="{ selected }"
      @click="open"
      @touchstart.passive="onTouchStart"
      @touchend="onTouchEnd"
      @touchmove.passive="onTouchEnd"
      @touchcancel="onTouchEnd"
      @contextmenu.prevent="emit('more', note)"
    >
      <van-checkbox v-if="selectable" :model-value="!!selected" class="select-box" @click.stop="emit('select', note)" />
      <div class="flex items-center gap-2 mb-1.5">
        <van-icon v-if="note.is_pinned" name="star" color="#86909c" />
        <span v-if="showProject" class="text-xs text-muted text-ellipsis ml-auto">
          {{ app.projectName(note.project_id) }}
        </span>
        <span v-else class="text-xs text-muted ml-auto">{{ smartTime(note.updated_at) }}</span>
      </div>
      <div class="title">{{ note.title || '（无标题）' }}</div>
      <div v-if="note.summary && note.summary !== note.title" class="summary line-clamp-2">{{ note.summary }}</div>

      <div v-if="note.images.length || note.has_audio" class="media-row">
        <img v-for="(img, i) in note.images.slice(0, 3)" :key="i" :src="img" class="thumb" loading="lazy" />
        <div v-for="a in note.attachments.filter((x) => x.file_type === 'audio').slice(0, 2)" :key="a.id" class="audio-chip">
          <van-icon name="volume-o" /> {{ duration(a.duration) || '语音' }}
          <van-icon v-if="a.asr_status === 3" name="passed" color="#07c160" />
          <van-loading v-else-if="a.asr_status === 1 || a.asr_status === 2" size="12" />
        </div>
      </div>

      <div class="meta">
        <span v-for="t in note.tags" :key="t.id" class="meta-item">#{{ t.name }}</span>
        <span v-if="note.attachment_count" class="meta-item"><van-icon name="link-o" />{{ note.attachment_count }}</span>
        <span v-if="note.next_reminder" class="meta-item" :class="{ 'text-danger': overdue }">
          <van-icon name="bell" />{{ smartTime(note.next_reminder.remind_at) }}
        </span>
        <span v-if="showProject" class="meta-item ml-auto">{{ smartTime(note.updated_at) }}</span>
      </div>
    </div>
    <template #right>
      <van-button square type="warning" text="置顶" class="h-full" @click.stop="emit('pin', note)" />
      <van-button square type="primary" text="归档" class="h-full" @click.stop="emit('archive', note)" />
      <van-button square type="danger" text="删除" class="h-full" @click.stop="emit('delete', note)" />
    </template>
  </van-swipe-cell>
</template>

<style scoped>
.note-card { background: #fff; padding: 12px 14px; cursor: pointer; position: relative; }
.note-card.selected { background: #e8f3ff; }
.select-box { position: absolute; right: 12px; bottom: 12px; }
.note-card:active { background: #f7f8fa; }
.title { font-size: 15px; font-weight: 500; line-height: 1.4; word-break: break-all; }
.summary { font-size: 13px; color: #646566; margin-top: 4px; line-height: 1.5; white-space: pre-line; }
.media-row { display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; }
.thumb { width: 64px; height: 64px; object-fit: cover; border-radius: 6px; background: #f2f3f5; }
.audio-chip { display: inline-flex; align-items: center; gap: 4px; height: 26px; padding: 0 10px; border-radius: 13px; background: #f2f3f5; font-size: 12px; color: #646566; }
.meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; margin-top: 8px; font-size: 12px; color: #969799; }
.meta-item { display: inline-flex; align-items: center; gap: 3px; }
.text-muted { color: #969799; }
.text-danger { color: #ee0a24; }
</style>
