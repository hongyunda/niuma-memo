<script setup lang="ts">
import { computed, h } from 'vue'
import { useRouter } from 'vue-router'
import type { Reminder } from '@/shared/api/types'
import { CHANNELS, REPEAT_TYPES, dayjs, smartTime } from '@/shared/utils/format'
import { snoozeOptions } from '@/shared/composables/useReminderForm'

const props = defineProps<{ reminder: Reminder; showActions?: boolean; showDate?: boolean }>()
const emit = defineEmits<{ (e: 'done', r: Reminder): void; (e: 'snooze', r: Reminder, minutes: number): void; (e: 'edit', r: Reminder): void; (e: 'delete', r: Reminder): void }>()
const router = useRouter()

const overdue = computed(() => (props.reminder.status === 1 || props.reminder.status === 2) && dayjs(props.reminder.remind_at).isBefore(dayjs()))
const finished = computed(() => props.reminder.status >= 3)
const channels = computed(() => props.reminder.channels.split(',').filter(Boolean).map((c) => CHANNELS[c] || c).join(' / '))
const snoozeMenu = computed(() => snoozeOptions().map((o) => ({ label: o.label, key: o.minutes })))
const moreMenu = [
  { label: '编辑', key: 'edit', icon: () => h('i', { class: 'i-tabler-edit' }) },
  { label: '删除', key: 'delete', icon: () => h('i', { class: 'i-tabler-trash' }), props: { style: 'color:#ef4444' } },
]

function open() {
  if (props.reminder.note_id) router.push(`/notes/${props.reminder.note_id}`)
  else emit('edit', props.reminder)
}
</script>

<template>
  <div class="rr" :class="{ overdue, finished }" @click="open">
    <div class="rr-time">
      <b>{{ dayjs(reminder.remind_at).format('HH:mm') }}</b>
      <span>{{ showDate === false ? '' : dayjs(reminder.remind_at).format('M/D') }}</span>
    </div>
    <div class="rr-body">
      <div class="rr-title">{{ reminder.title }}</div>
      <div class="rr-meta">
        <span v-if="reminder.note && reminder.note.title !== reminder.title" class="text-ellipsis" style="max-width: 200px"><i class="i-tabler-file-text" /> {{ reminder.note.title }}</span>
        <span v-if="reminder.repeat_type"><i class="i-tabler-repeat" /> {{ REPEAT_TYPES[reminder.repeat_type] }}</span>
        <span>{{ channels }}</span>
        <span v-if="reminder.snooze_until && reminder.status === 1">稍后 {{ smartTime(reminder.snooze_until) }}</span>
        <span v-if="overdue" class="red">已过期 {{ smartTime(reminder.remind_at) }}</span>
        <span v-if="reminder.status === 2" class="orange">已推送</span>
        <span v-if="reminder.status === 3" class="green">已完成</span>
      </div>
    </div>
    <div v-if="showActions && !finished" class="rr-actions" @click.stop>
      <n-dropdown :options="snoozeMenu" trigger="click" @select="(m: number) => emit('snooze', reminder, m)">
        <n-tooltip><template #trigger><n-button size="tiny" quaternary circle><template #icon><i class="i-tabler-clock" /></template></n-button></template>稍后提醒</n-tooltip>
      </n-dropdown>
      <n-tooltip><template #trigger><n-button size="tiny" type="success" circle @click="emit('done', reminder)"><template #icon><i class="i-tabler-check" /></template></n-button></template>完成</n-tooltip>
      <n-dropdown :options="moreMenu" trigger="click" @select="(k: string) => (k === 'edit' ? emit('edit', reminder) : emit('delete', reminder))">
        <n-button size="tiny" quaternary circle><template #icon><i class="i-tabler-dots" /></template></n-button>
      </n-dropdown>
    </div>
  </div>
</template>

<style scoped>
.rr { display: flex; align-items: center; gap: 12px; padding: 9px 14px; cursor: pointer; transition: background var(--dur); }
.rr:hover { background: var(--bg-hover); }
.rr-time { display: flex; flex-direction: column; align-items: center; min-width: 46px; color: var(--t-1); }
.rr-time b { font-size: 15px; font-weight: 600; }
.rr-time span { font-size: 11px; color: var(--t-3); }
.overdue .rr-time { color: var(--c-danger); }
.finished { opacity: 0.6; }
.finished .rr-time { color: var(--t-3); }
.finished .rr-title { text-decoration: line-through; }
.rr-body { flex: 1; min-width: 0; }
.rr-title { font-size: 14px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rr-meta { display: flex; flex-wrap: wrap; gap: 2px 10px; font-size: 12px; color: var(--t-3); margin-top: 2px; }
.rr-meta i { font-size: 12px; vertical-align: -1px; }
.red { color: var(--c-danger); } .orange, .green { color: var(--t-3); }
.rr-actions { display: flex; align-items: center; gap: 2px; opacity: 0; transition: opacity var(--dur); }
.rr:hover .rr-actions { opacity: 1; }
</style>
