import { computed } from 'vue'
import type { Reminder, ReminderPayload } from '@/shared/api/types'
import { dayjs } from '@/shared/utils/format'

/** 提醒表单共用：快捷时间、稍后选项、payload 组装 */
export function quickTimes() {
  const now = dayjs()
  const tonight = now.hour(20).minute(0).second(0)
  return [
    { label: '1 小时后', at: now.add(1, 'hour').minute(0).second(0) },
    { label: '今晚 20:00', at: tonight.isAfter(now) ? tonight : tonight.add(1, 'day') },
    { label: '明早 9:00', at: now.add(1, 'day').hour(9).minute(0).second(0) },
    { label: '明天下午 14:00', at: now.add(1, 'day').hour(14).minute(0).second(0) },
    { label: '下周一 9:00', at: now.day(8).hour(9).minute(0).second(0) },
    { label: '下月 1 号 9:00', at: now.add(1, 'month').date(1).hour(9).minute(0).second(0) },
  ]
}

export function snoozeOptions() {
  const now = dayjs()
  const tomorrow9 = now.add(1, 'day').hour(9).minute(0)
  const tonight = now.hour(20).minute(0)
  const minutesUntil = (d: dayjs.Dayjs) => Math.max(1, Math.round(d.diff(now, 'minute')))
  return [
    { label: '10 分钟后', minutes: 10 },
    { label: '1 小时后', minutes: 60 },
    { label: '今晚 20:00', minutes: minutesUntil(tonight.isAfter(now) ? tonight : tomorrow9) },
    { label: '明早 9:00', minutes: minutesUntil(tomorrow9) },
    { label: '下周一 9:00', minutes: minutesUntil(now.day(8).hour(9).minute(0)) },
  ]
}

export function reminderDefaults(r: Reminder | null | undefined, defaultChannels: string[] | undefined) {
  const at = r ? dayjs(r.remind_at) : dayjs().add(1, 'hour').minute(0).second(0)
  return {
    title: r?.title || '',
    at: at.valueOf(),
    repeat: r?.repeat_type ?? 0,
    advance: r?.advance_minutes ?? 0,
    channels: r ? r.channels.split(',').filter(Boolean) : defaultChannels?.length ? defaultChannels : ['webpush'],
  }
}

export function toReminderPayload(f: { title: string; at: number; repeat: number; advance: number; channels: string[] }): ReminderPayload {
  return { title: f.title.trim(), remind_at: dayjs(f.at).format('YYYY-MM-DD HH:mm'), repeat_type: f.repeat, advance_minutes: f.advance, channels: f.channels }
}

export const useOverdue = (r: () => Reminder) => computed(() => (r().status === 1 || r().status === 2) && dayjs(r().remind_at).isBefore(dayjs()))
