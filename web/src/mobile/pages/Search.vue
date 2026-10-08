<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { searchApi } from '@/shared/api'
import type { Attachment, NoteQuery } from '@/shared/api/types'
import { FILE_TYPES } from '@/shared/utils/format'
import PageHeader from '@/mobile/components/PageHeader.vue'
import NoteList from '@/mobile/components/NoteList.vue'

const route = useRoute()
const router = useRouter()
const q = ref((route.query.q as string) || '')
const committed = ref(q.value)
const attachments = ref<Attachment[]>([])

const query = computed<NoteQuery>(() => ({ keyword: committed.value, archived: 'all' }))

async function search() {
  const kw = q.value.trim()
  committed.value = kw
  router.replace({ query: kw ? { q: kw } : {} })
  if (!kw) return (attachments.value = [])
  attachments.value = (await searchApi.query(kw)).attachments
}

function openAttachment(a: Attachment) {
  a.note_id ? router.push(`/notes/${a.note_id}`) : window.open(a.url, '_blank')
}

watch(committed, () => committed.value && searchApi.query(committed.value).then((r) => (attachments.value = r.attachments)), { immediate: true })
</script>

<template>
  <div class="page">
    <PageHeader title="搜索" />
    <div class="desktop-container !pt-0">
      <van-search v-model="q" placeholder="搜标题、正文、文件名、语音内容" shape="round" show-action autofocus @search="search" @cancel="router.back()" />
      <template v-if="committed">
        <div v-if="attachments.length" class="mx-3 mb-2 bg-white rounded-xl overflow-hidden">
          <div class="text-xs text-gray-400 px-3 pt-2">匹配的文件 / 录音</div>
          <div v-for="a in attachments" :key="a.id" class="flex items-center gap-3 px-3 py-2 cursor-pointer border-b border-gray-50" @click="openAttachment(a)">
            <van-icon :name="FILE_TYPES[a.file_type].icon" :color="FILE_TYPES[a.file_type].color" size="20" />
            <div class="flex-1 min-w-0">
              <div class="text-sm text-ellipsis">{{ a.original_name }}</div>
              <div v-if="a.transcript" class="text-xs text-gray-500 line-clamp-2">{{ a.transcript }}</div>
              <div v-if="a.note" class="text-xs text-gray-400">📝 {{ a.note.title }}</div>
            </div>
          </div>
        </div>
        <NoteList :query="query" show-project :pull-refresh="false" empty-text="没有找到相关记录" />
      </template>
      <van-empty v-else image="search" description="输入关键词开始搜索" />
    </div>
  </div>
</template>
