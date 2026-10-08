<script setup lang="ts">
import { computed } from 'vue'
import { useTranscribe } from '@/shared/composables/useTranscribe'
import { showConfirmDialog, showImagePreview, showToast } from 'vant'
import { attachmentApi } from '@/shared/api'
import type { Attachment } from '@/shared/api/types'
import { FILE_TYPES, duration, fileSize } from '@/shared/utils/format'

const props = withDefaults(defineProps<{ items: Attachment[]; editable?: boolean; asrEnabled?: boolean; compact?: boolean }>(), {
  editable: true,
  asrEnabled: false,
  compact: false,
})
const emit = defineEmits<{ (e: 'remove', id: number): void; (e: 'insert-text', text: string): void; (e: 'update', a: Attachment): void }>()
const items = computed(() => props.items)
const { transcribe } = useTranscribe(items, () => props.asrEnabled, (a) => emit('update', a))


function images() {
  return props.items.filter((a) => a.file_type === 'image')
}

function preview(a: Attachment) {
  const imgs = images()
  showImagePreview({ images: imgs.map((i) => i.url), startPosition: imgs.findIndex((i) => i.id === a.id), closeable: true })
}

function openFile(a: Attachment) {
  window.open(a.url, '_blank')
}

async function remove(a: Attachment) {
  try {
    await showConfirmDialog({ title: '删除附件', message: `确定删除「${a.original_name}」？文件将无法恢复。` })
  } catch {
    return
  }
  await attachmentApi.remove(a.id)
  emit('remove', a.id)
}


function copy(text: string) {
  navigator.clipboard?.writeText(text).then(() => showToast('已复制'))
}

</script>

<template>
  <div v-if="items.length" class="attachments">
    <div v-if="images().length" class="img-grid" :class="{ compact }">
      <div v-for="a in images()" :key="a.id" class="img-cell">
        <img :src="a.thumb_url || a.url" :alt="a.original_name" loading="lazy" @click="preview(a)" />
        <van-icon v-if="editable" name="clear" class="del" @click.stop="remove(a)" />
      </div>
    </div>

    <div v-for="a in items.filter((x) => x.file_type !== 'image')" :key="a.id" class="file-item">
      <template v-if="a.file_type === 'audio'">
        <div class="flex items-center gap-2">
          <van-icon name="volume-o" :color="FILE_TYPES.audio.color" size="18" />
          <span class="text-sm text-ellipsis flex-1">{{ a.original_name }}</span>
          <span class="text-xs text-gray-400">{{ duration(a.duration) }}</span>
          <van-icon v-if="editable" name="delete-o" class="text-gray-400 cursor-pointer" @click="remove(a)" />
        </div>
        <audio :src="a.url" controls preload="metadata" class="w-full mt-2" />
        <div class="asr">
          <template v-if="a.asr_status === 3 && a.transcript">
            <div class="asr-text">{{ a.transcript }}</div>
            <div class="flex gap-3 text-xs mt-1">
              <span class="link" @click="copy(a.transcript!)">复制</span>
              <span v-if="editable" class="link" @click="emit('insert-text', a.transcript!)">插入正文</span>
              <span v-if="asrEnabled" class="link text-gray-400" @click="transcribe(a, true)">重新转写</span>
            </div>
          </template>
          <div v-else-if="a.asr_status === 1 || a.asr_status === 2" class="text-xs text-gray-500 flex items-center gap-1">
            <van-loading size="12" /> 正在转文字…
          </div>
          <div v-else-if="a.asr_status === 4" class="text-xs text-red-500 flex items-center gap-2">
            转写失败：{{ a.asr_error || '未知错误' }}
            <span v-if="asrEnabled" class="link" @click="transcribe(a, true)">重试</span>
          </div>
          <div v-else-if="a.asr_status === 3" class="text-xs text-gray-400">未识别到有效语音</div>
          <div v-else-if="asrEnabled" class="text-xs"><span class="link" @click="transcribe(a)">转成文字</span></div>
        </div>
      </template>

      <template v-else-if="a.file_type === 'video'">
        <div class="flex items-center gap-2 mb-2">
          <van-icon name="video-o" :color="FILE_TYPES.video.color" size="18" />
          <span class="text-sm text-ellipsis flex-1">{{ a.original_name }}</span>
          <span class="text-xs text-gray-400">{{ duration(a.duration) }} · {{ fileSize(a.size) }}</span>
          <van-icon v-if="editable" name="delete-o" class="text-gray-400 cursor-pointer" @click="remove(a)" />
        </div>
        <video :src="a.url" :poster="a.thumb_url || undefined" controls preload="none" playsinline class="w-full rounded-lg bg-black max-h-72" />
        <div v-if="a.asr_status === 3 && a.transcript" class="asr"><div class="asr-text">{{ a.transcript }}</div></div>
        <div v-else-if="asrEnabled && a.asr_status !== 1 && a.asr_status !== 2" class="text-xs mt-1"><span class="link" @click="transcribe(a)">提取语音转文字</span></div>
        <div v-else-if="a.asr_status === 1 || a.asr_status === 2" class="text-xs text-gray-500 mt-1 flex items-center gap-1"><van-loading size="12" /> 正在转文字…</div>
      </template>

      <template v-else>
        <div class="flex items-center gap-3 cursor-pointer" @click="openFile(a)">
          <div class="file-icon" :style="{ background: FILE_TYPES[a.file_type].color }">{{ FILE_TYPES[a.file_type].label }}</div>
          <div class="flex-1 min-w-0">
            <div class="text-sm text-ellipsis">{{ a.original_name }}</div>
            <div class="text-xs text-gray-400">{{ fileSize(a.size) }}</div>
          </div>
          <a :href="a.url + '?download=1'" class="text-gray-400" @click.stop><van-icon name="down" /></a>
          <van-icon v-if="editable" name="delete-o" class="text-gray-400" @click.stop="remove(a)" />
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.attachments { display: flex; flex-direction: column; gap: 10px; }
.img-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.img-grid.compact { grid-template-columns: repeat(4, 1fr); }
.img-cell { position: relative; aspect-ratio: 1; }
.img-cell img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; background: #f2f3f5; cursor: zoom-in; }
.img-cell .del { position: absolute; top: -6px; right: -6px; font-size: 20px; color: #fff; background: rgba(0,0,0,.55); border-radius: 50%; cursor: pointer; }
.file-item { background: #f7f8fa; border-radius: 10px; padding: 10px 12px; }
.file-icon { width: 40px; height: 40px; border-radius: 8px; color: #fff; font-size: 11px; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.asr { margin-top: 8px; }
.asr-text { font-size: 14px; line-height: 1.6; white-space: pre-wrap; color: #323233; background: #fff; border-radius: 8px; padding: 8px 10px; }
.link { color: var(--van-primary-color); cursor: pointer; }
audio { height: 36px; }
</style>
