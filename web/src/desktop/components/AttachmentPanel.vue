<script setup lang="ts">
import { computed } from 'vue'
import { attachmentApi } from '@/shared/api'
import type { Attachment } from '@/shared/api/types'
import { FILE_TYPES, duration, fileSize } from '@/shared/utils/format'
import { useTranscribe } from '@/shared/composables/useTranscribe'
import { feedback } from '@/shared/ui/feedback'

const props = withDefaults(defineProps<{ items: Attachment[]; editable?: boolean; asrEnabled?: boolean }>(), { editable: true, asrEnabled: false })
const emit = defineEmits<{ (e: 'remove', id: number): void; (e: 'insert-text', text: string): void; (e: 'update', a: Attachment): void }>()

const items = computed(() => props.items)
const images = computed(() => props.items.filter((a) => a.file_type === 'image'))
const others = computed(() => props.items.filter((a) => a.file_type !== 'image'))
const { transcribe } = useTranscribe(items, () => props.asrEnabled, (a) => emit('update', a))

async function remove(a: Attachment) {
  await attachmentApi.remove(a.id)
  emit('remove', a.id)
  feedback.success('已删除')
}
function copy(text: string) {
  navigator.clipboard?.writeText(text).then(() => feedback.success('已复制'))
}
</script>

<template>
  <div v-if="items.length" class="ap">
    <n-image-group v-if="images.length">
      <div class="ap-grid">
        <div v-for="a in images" :key="a.id" class="ap-img">
          <n-image :src="a.thumb_url || a.url" :preview-src="a.url" object-fit="cover" lazy :img-props="{ alt: a.original_name }" />
          <n-popconfirm v-if="editable" positive-text="删除" negative-text="取消" @positive-click="remove(a)">
            <template #trigger><button class="ap-del" title="删除"><i class="i-tabler-x" /></button></template>
            删除「{{ a.original_name }}」？文件无法恢复。
          </n-popconfirm>
        </div>
      </div>
    </n-image-group>

    <div v-for="a in others" :key="a.id" class="ap-file">
      <template v-if="a.file_type === 'audio'">
        <div class="ap-head">
          <i class="i-tabler-microphone" :style="{ color: FILE_TYPES.audio.color }" />
          <span class="name">{{ a.original_name }}</span>
          <span class="meta">{{ duration(a.duration) }} · {{ fileSize(a.size) }}</span>
          <n-popconfirm v-if="editable" positive-text="删除" negative-text="取消" @positive-click="remove(a)">
            <template #trigger><button class="ap-act" title="删除"><i class="i-tabler-trash" /></button></template>
            删除这段录音？
          </n-popconfirm>
        </div>
        <audio :src="a.url" controls preload="metadata" class="w-full mt-2" />
        <div class="ap-asr">
          <template v-if="a.asr_status === 3 && a.transcript">
            <div class="ap-text">{{ a.transcript }}</div>
            <div class="ap-links">
              <a @click="copy(a.transcript!)">复制</a>
              <a v-if="editable" @click="emit('insert-text', a.transcript!)">插入正文</a>
              <a v-if="asrEnabled" class="muted" @click="transcribe(a, true)">重新转写</a>
            </div>
          </template>
          <div v-else-if="a.asr_status === 1 || a.asr_status === 2" class="ap-status"><span class="spin" /> 正在转文字…</div>
          <div v-else-if="a.asr_status === 4" class="ap-status err">转写失败：{{ a.asr_error || '未知错误' }} <a v-if="asrEnabled" @click="transcribe(a, true)">重试</a></div>
          <div v-else-if="a.asr_status === 3" class="ap-status">未识别到有效语音</div>
          <div v-else-if="asrEnabled" class="ap-links"><a @click="transcribe(a)">转成文字</a></div>
        </div>
      </template>

      <template v-else-if="a.file_type === 'video'">
        <div class="ap-head">
          <i class="i-tabler-video" :style="{ color: FILE_TYPES.video.color }" />
          <span class="name">{{ a.original_name }}</span>
          <span class="meta">{{ duration(a.duration) }} · {{ fileSize(a.size) }}</span>
          <n-popconfirm v-if="editable" positive-text="删除" negative-text="取消" @positive-click="remove(a)">
            <template #trigger><button class="ap-act" title="删除"><i class="i-tabler-trash" /></button></template>
            删除这个视频？
          </n-popconfirm>
        </div>
        <video :src="a.url" :poster="a.thumb_url || undefined" controls preload="none" class="ap-video" />
        <div v-if="a.asr_status === 3 && a.transcript" class="ap-asr"><div class="ap-text">{{ a.transcript }}</div></div>
        <div v-else-if="a.asr_status === 1 || a.asr_status === 2" class="ap-status mt-2"><span class="spin" /> 正在转文字…</div>
        <div v-else-if="asrEnabled" class="ap-links mt-1"><a @click="transcribe(a)">提取语音转文字</a></div>
      </template>

      <template v-else>
        <div class="ap-head doc">
          <span class="ap-icon" :style="{ background: FILE_TYPES[a.file_type].color }">{{ FILE_TYPES[a.file_type].label }}</span>
          <div class="min-w-0 flex-1">
            <a :href="a.url" target="_blank" class="name link">{{ a.original_name }}</a>
            <div class="meta">{{ fileSize(a.size) }}</div>
          </div>
          <a :href="a.url" target="_blank" class="ap-act" title="预览"><i class="i-tabler-eye" /></a>
          <a :href="a.url + '?download=1'" class="ap-act" title="下载"><i class="i-tabler-download" /></a>
          <n-popconfirm v-if="editable" positive-text="删除" negative-text="取消" @positive-click="remove(a)">
            <template #trigger><button class="ap-act" title="删除"><i class="i-tabler-trash" /></button></template>
            删除「{{ a.original_name }}」？
          </n-popconfirm>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.ap { display: flex; flex-direction: column; gap: 10px; }
.ap-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; }
.ap-img { position: relative; aspect-ratio: 4/3; border-radius: 10px; overflow: hidden; background: var(--bg-muted); }
.ap-img :deep(.n-image), .ap-img :deep(img) { width: 100%; height: 100%; display: block; }
.ap-img :deep(img) { object-fit: cover; }
.ap-del { position: absolute; top: 6px; right: 6px; width: 24px; height: 24px; border: 0; border-radius: 6px; background: rgba(0,0,0,0.55); color: #fff; cursor: pointer; opacity: 0; transition: opacity var(--dur); display: inline-flex; align-items: center; justify-content: center; }
.ap-img:hover .ap-del { opacity: 1; }
.ap-file { background: #f8f9fb; border: 1px solid var(--b-2); border-radius: 12px; padding: 10px 12px; }
.ap-head { display: flex; align-items: center; gap: 10px; }
.ap-head > i { font-size: 18px; }
.name { flex: 1; min-width: 0; font-size: 13.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--t-1); }
.name.link:hover { color: var(--c-primary); text-decoration: underline; }
.meta { font-size: 12px; color: var(--t-3); white-space: nowrap; }
.ap-act { width: 28px; height: 28px; border: 0; border-radius: 7px; background: transparent; color: var(--t-3); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; }
.ap-act:hover { background: #eceef1; color: var(--t-1); }
.ap-icon { width: 40px; height: 40px; border-radius: 9px; color: #fff; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.ap-video { width: 100%; max-height: 360px; border-radius: 10px; background: #000; margin-top: 8px; }
.ap-asr { margin-top: 8px; }
.ap-text { font-size: 14px; line-height: 1.7; white-space: pre-wrap; background: #fff; border-radius: 8px; padding: 10px 12px; color: var(--t-1); border: 1px solid var(--b-2); }
.ap-links { display: flex; gap: 14px; font-size: 12.5px; margin-top: 6px; }
.ap-links a { color: var(--c-primary); cursor: pointer; }
.ap-links a.muted { color: var(--t-3); }
.ap-status { font-size: 12.5px; color: var(--t-3); display: flex; align-items: center; gap: 6px; }
.ap-status.err { color: var(--c-danger); }
.ap-status a { color: var(--c-primary); cursor: pointer; }
.spin { width: 12px; height: 12px; border: 2px solid var(--b-1); border-top-color: var(--c-primary); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
audio { height: 36px; }
</style>
