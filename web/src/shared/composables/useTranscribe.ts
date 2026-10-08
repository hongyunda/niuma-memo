import { onBeforeUnmount, watch, type Ref } from 'vue'
import { attachmentApi } from '@/shared/api'
import type { Attachment } from '@/shared/api/types'
import { feedback } from '@/shared/ui/feedback'

/**
 * 语音转文字轮询：排队 / 转写中的附件每 4 秒推进一次，直到完成或失败
 */
export function useTranscribe(items: Ref<Attachment[]>, enabled: () => boolean, onUpdate?: (a: Attachment) => void) {
  const timers = new Map<number, number>()

  async function transcribe(a: Attachment, force = false) {
    if (a.asr_status !== 3 || force) a.asr_status = a.asr_status === 2 ? 2 : 1
    try {
      const res = await attachmentApi.transcribe(a.id, force)
      Object.assign(a, res)
      onUpdate?.(a)
      if (res.asr_status === 1 || res.asr_status === 2) schedule(a)
      else if (res.asr_status === 4) feedback.error(res.asr_error || '转写失败')
    } catch (e) {
      a.asr_status = 4
      a.asr_error = (e as Error).message
    }
  }

  function schedule(a: Attachment, delay = 4000) {
    clearTimeout(timers.get(a.id))
    timers.set(a.id, window.setTimeout(() => transcribe(a), delay))
  }

  watch(() => items.value.map((a) => `${a.id}:${a.asr_status}`).join(','), () => {
    if (!enabled()) return
    for (const a of items.value) {
      if ((a.file_type === 'audio' || a.file_type === 'video') && (a.asr_status === 1 || a.asr_status === 2) && !timers.has(a.id)) schedule(a, 800)
    }
  }, { immediate: true })

  onBeforeUnmount(() => timers.forEach((t) => clearTimeout(t)))

  return { transcribe }
}
