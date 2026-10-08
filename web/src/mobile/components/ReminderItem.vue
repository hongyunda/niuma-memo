<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import type { Reminder } from '@/shared/api/types'
import { CHANNELS, REPEAT_TYPES, dayjs, smartTime } from '@/shared/utils/format'

const props = defineProps<{ reminder: Reminder; showActions?: boolean }>()
const emit = defineEmits<{ (e: 'done', r: Reminder): void; (e: 'snooze', r: Reminder): void; (e: 'edit', r: Reminder): void; (e: 'delete', r: Reminder): void }>()
const router = useRouter()

const overdue = computed(() => (props.reminder.status === 1 || props.reminder.status === 2) && dayjs(props.reminder.remind_at).isBefore(dayjs()))
const finished = computed(() => props.reminder.status >= 3)
const channels = computed(() => props.reminder.channels.split(',').filter(Boolean).map((c) => CHANNELS[c] || c).join(' / '))

function open() {
  if (props.reminder.note_id) router.push(`/notes/${props.reminder.note_id}`)
  else emit('edit', props.reminder)
}
</script>

<template>
  <van-swipe-cell :disabled="!showActions">
    <div class="item" :class="{ overdue, finished }" @click="open">
      <div class="time">
        <div class="text-base font-medium">{{ dayjs(reminder.remind_at).format('HH:mm') }}</div>
        <div class="text-xs">{{ dayjs(reminder.remind_at).format('M/D') }}</div>
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-medium text-ellipsis">{{ reminder.title }}</div>
        <div class="text-xs text-gray-400 mt-0.5 flex flex-wrap gap-x-2">
          <span v-if="reminder.note && reminder.note.title !== reminder.title" class="text-ellipsis max-w-40">📝 {{ reminder.note.title }}</span>
          <span v-if="reminder.repeat_type">🔁 {{ REPEAT_TYPES[reminder.repeat_type] }}</span>
          <span>{{ channels }}</span>
          <span v-if="reminder.snooze_until && reminder.status === 1">稍后 {{ smartTime(reminder.snooze_until) }}</span>
          <span v-if="overdue" class="text-red-500">已过期 {{ smartTime(reminder.remind_at) }}</span>
          <span v-if="reminder.status === 2" class="text-orange-500">已推送</span>
          <span v-if="reminder.status === 3" class="text-green-600">已完成</span>
        </div>
      </div>
      <div v-if="showActions && !finished" class="flex items-center gap-1" @click.stop>
        <van-button size="small" round plain icon="clock-o" @click="emit('snooze', reminder)" />
        <van-button size="small" round type="success" icon="success" @click="emit('done', reminder)" />
      </div>
    </div>
    <template #right>
      <van-button square type="primary" text="编辑" class="h-full" @click.stop="emit('edit', reminder)" />
      <van-button square type="danger" text="删除" class="h-full" @click.stop="emit('delete', reminder)" />
    </template>
  </van-swipe-cell>
</template>

<style scoped>
.item { display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: #fff; cursor: pointer; }
.time { text-align: center; min-width: 46px; color: var(--t-1); }
.overdue .time { color: #ee0a24; }
.finished { opacity: 0.6; }
.finished .time { color: #969799; }
.finished .text-sm { text-decoration: line-through; }
</style>
