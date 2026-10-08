<script setup lang="ts">
import { computed } from 'vue'
import { showToast } from 'vant'
import { reminderApi } from '@/shared/api'
import type { Reminder } from '@/shared/api/types'
import { dayjs } from '@/shared/utils/format'
import { snoozeOptions } from '@/shared/composables/useReminderForm'

const props = defineProps<{ reminder: Reminder | null }>()
const emit = defineEmits<{ (e: 'close'): void; (e: 'snoozed', r: Reminder): void }>()

const options = computed(() => snoozeOptions().map((o) => ({ name: o.label, minutes: o.minutes })))

async function pick(o: { minutes: number }) {
  if (!props.reminder) return
  const r = await reminderApi.snooze(props.reminder.id, o.minutes)
  showToast(`已推迟到 ${dayjs(r.snooze_until).format('M月D日 HH:mm')}`)
  emit('snoozed', r)
}
</script>

<template>
  <van-action-sheet :show="!!reminder" :actions="options" description="稍后提醒" cancel-text="取消" close-on-click-action @select="pick" @update:show="(v: boolean) => !v && emit('close')" />
</template>
