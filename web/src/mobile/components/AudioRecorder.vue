<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { showToast } from 'vant'
import { VoiceRecorder, recorderSupported, type RecordingResult } from '@/shared/utils/recorder'
import { duration as fmtDuration } from '@/shared/utils/format'

const props = defineProps<{ show: boolean }>()
const emit = defineEmits<{ (e: 'update:show', v: boolean): void; (e: 'recorded', r: RecordingResult): void }>()

const recorder = new VoiceRecorder()
const recording = ref(false)
const elapsed = ref(0)
const levels = ref<number[]>(Array(24).fill(0.05))
let timer: number | undefined

async function start() {
  if (!recorderSupported()) return showToast('当前浏览器不支持录音，请使用 HTTPS 或更新浏览器')
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
    showToast((e as Error).name === 'NotAllowedError' ? '没有麦克风权限' : '无法开始录音：' + (e as Error).message)
  }
}

async function stop() {
  if (!recording.value) return
  clearInterval(timer)
  recording.value = false
  const result = await recorder.stop()
  if (result.duration < 1 || result.blob.size < 1000) {
    showToast('录音太短了')
    return
  }
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
    levels.value = Array(24).fill(0.05)
    start()
  } else if (recording.value) {
    cancel()
  }
})

onBeforeUnmount(() => {
  clearInterval(timer)
  recorder.cancel()
})
</script>

<template>
  <van-popup :show="show" position="bottom" round safe-area-inset-bottom :close-on-click-overlay="false" @update:show="emit('update:show', $event)">
    <div class="p-6 text-center">
      <div class="text-base font-medium mb-1">{{ recording ? '正在录音…' : '准备中' }}</div>
      <div class="text-3xl font-mono tabular-nums my-3">{{ fmtDuration(elapsed) || '0:00' }}</div>
      <div class="wave">
        <span v-for="(l, i) in levels" :key="i" :style="{ height: Math.round(6 + l * 54) + 'px' }" />
      </div>
      <div class="text-xs text-gray-400 mb-5">最长 30 分钟，停止后自动上传并转成文字</div>
      <div class="flex justify-center gap-6">
        <van-button round plain icon="cross" @click="cancel">取消</van-button>
        <van-button round type="danger" icon="stop" :disabled="!recording" @click="stop">停止并保存</van-button>
      </div>
    </div>
  </van-popup>
</template>

<style scoped>
.wave { display: flex; align-items: center; justify-content: center; gap: 4px; height: 64px; margin-bottom: 12px; }
.wave span { width: 5px; border-radius: 3px; background: linear-gradient(180deg, #3fa2ff, #0d6fd6); transition: height 0.12s; }
</style>
