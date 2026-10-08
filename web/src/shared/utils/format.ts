import dayjs from 'dayjs'
import 'dayjs/locale/zh-cn'
import relativeTime from 'dayjs/plugin/relativeTime'
import calendar from 'dayjs/plugin/calendar'
import type { FileType } from '@/shared/api/types'

dayjs.locale('zh-cn')
dayjs.extend(relativeTime)
dayjs.extend(calendar)

export { dayjs }

export const fromNow = (d?: string | null) => (d ? dayjs(d).fromNow() : '')

export const smartTime = (d?: string | null) => {
  if (!d) return ''
  const t = dayjs(d)
  const now = dayjs()
  if (t.isSame(now, 'day')) return t.format('HH:mm')
  if (t.isSame(now.subtract(1, 'day'), 'day')) return '昨天 ' + t.format('HH:mm')
  if (t.isSame(now.add(1, 'day'), 'day')) return '明天 ' + t.format('HH:mm')
  if (t.isSame(now, 'year')) return t.format('M月D日 HH:mm')
  return t.format('YYYY-M-D HH:mm')
}

export const fmtDate = (d?: string | null, f = 'YYYY-MM-DD HH:mm') => (d ? dayjs(d).format(f) : '')

export const fileSize = (bytes: number) => {
  if (!bytes) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  let i = 0
  let n = bytes
  while (n >= 1024 && i < units.length - 1) {
    n /= 1024
    i++
  }
  return `${n.toFixed(i === 0 ? 0 : 1)} ${units[i]}`
}

export const duration = (sec: number) => {
  if (!sec) return ''
  const m = Math.floor(sec / 60)
  const s = sec % 60
  if (m >= 60) return `${Math.floor(m / 60)}:${String(m % 60).padStart(2, '0')}:${String(s).padStart(2, '0')}`
  return `${m}:${String(s).padStart(2, '0')}`
}

export const FILE_TYPES: Record<FileType, { label: string; icon: string; color: string }> = {
  image: { label: '图片', icon: 'photo-o', color: '#2b7fff' },
  audio: { label: '音频', icon: 'music-o', color: '#7c3aed' },
  video: { label: '视频', icon: 'video-o', color: '#e5484d' },
  pdf: { label: 'PDF', icon: 'description', color: '#e5484d' },
  word: { label: 'Word', icon: 'description', color: '#2b5fd9' },
  excel: { label: 'Excel', icon: 'chart-trending-o', color: '#1a9e5c' },
  ppt: { label: 'PPT', icon: 'tv-o', color: '#e0702a' },
  other: { label: '文件', icon: 'description', color: '#86909c' },
}

export const REPEAT_TYPES: Record<number, string> = { 0: '不重复', 1: '每天', 2: '每周', 3: '每月', 4: '每年', 5: '工作日' }

export const CHANNELS: Record<string, string> = { webpush: '浏览器通知', bark: 'Bark (iOS)', wecom: '企业微信' }
