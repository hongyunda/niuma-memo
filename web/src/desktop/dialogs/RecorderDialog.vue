<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { VoiceRecorder, recorderSupported, type RecordingResult } from '@/shared/utils/recorder'
import { duration as fmtDuration } from '@/shared/utils/format'
import { feedback } from '@/shared/ui/feedback'

const props = defineProps<{ show: boolean }>()
const emit = defineEmits<{ (e: 'update:show', v: boolean): void; (e: 'recorded', r: RecordingResult): void }>()

const recorder = new VoiceRecorder()
const recording = ref(false)
const elapsed = ref(0)
const levels = ref<number[]>(Array(32).fill(0.05))
let timer: number | undefined

async function start() {
  if (!recorderSupported()) return feedback.error('当前浏览器不支持录音，请使用 HTTPS 访问')
  try {
    await recorder.start()
    recording.value = true
    elapsed.value = 0
    timer = window.setInterval(() => {
      elapsed.value = recorder.elapsed()
      levels.value = [...levels.value.slice(1), Math.max(0.05, recorder.level())]
      if (elapsed.value >= 60 * 30) stop()
    }, 120)
  } catch (e) {
    feedback.error((e as Error).name === 'NotAllowedError' ? '没有麦克风权限' : '无法开始录音：' + (e as Error).message)
    emit('update:show', false)
  }
}

async function stop() {
  if (!recording.value) return
  clearInterval(timer)
  recording.value = false
  const result = await recorder.stop()
  if (result.duration < 1 || result.blob.size < 1000) return feedback.error('录音太短了')
  emit('recorded', result)
  emit('update:show', false)
}

function cancel() {
  clearInterval(timer)
  recorder.cancel()
  recording.value = false
  emit('update:show', false)
}

watch(() => props.show, (v) => {
  if (v) {
    levels.value = Array(32).fill(0.05)
    start()
  } else if (recording.value) cancel()
})
onBeforeUnmount(() => {
  clearInterval(timer)
  recorder.cancel()
})
</script>

<template>
  <n-modal :show="show" preset="card" title="录音" :style="{ width: '440px' }" :bordered="false" :mask-closable="false" @update:show="(v: boolean) => !v && cancel()">
    <div class="text-center">
      <div class="text-3xl font-mono tabular-nums mb-3">{{ fmtDuration(elapsed) || '0:00' }}</div>
      <div class="wave"><span v-for="(l, i) in levels" :key="i" :style="{ height: Math.round(6 + l * 54) + 'px' }" /></div>
      <div class="text-xs mb-4" style="color: var(--t-3)">最长 30 分钟，停止后自动上传并转成文字</div>
      <div class="flex justify-center gap-3">
        <n-button @click="cancel">取消</n-button>
        <n-button type="error" :disabled="!recording" @click="stop"><template #icon><i class="i-tabler-player-stop-filled" /></template>停止并保存</n-button>
      </div>
    </div>
  </n-modal>
</template>

<style scoped>
.wave { display: flex; align-items: center; justify-content: center; gap: 3px; height: 64px; margin-bottom: 10px; }
.wave span { width: 5px; border-radius: 3px; background: linear-gradient(180deg, #4d93ff, #1f6fe6); transition: height 0.12s; }
</style>
