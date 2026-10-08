<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { showConfirmDialog, showToast } from 'vant'
import { noteApi } from '@/shared/api'
import type { NoteListItem } from '@/shared/api/types'
import { fmtDate } from '@/shared/utils/format'
import { useAppStore } from '@/shared/stores/app'
import PageHeader from '@/mobile/components/PageHeader.vue'

const app = useAppStore()
const list = ref<NoteListItem[]>([])
const loading = ref(false)
const finished = ref(false)
const page = ref(1)

async function load(reset = false) {
  if (reset) {
    page.value = 1
    list.value = []
    finished.value = false
  }
  loading.value = true
  try {
    const res = await noteApi.trash(page.value)
    list.value.push(...res.list)
    finished.value = page.value >= res.last_page
    page.value++
  } finally {
    loading.value = false
  }
}

async function restore(n: NoteListItem) {
  await noteApi.restore(n.id)
  list.value = list.value.filter((x) => x.id !== n.id)
  showToast('已恢复')
  app.fetchProjects()
}

async function destroy(n: NoteListItem) {
  try {
    await showConfirmDialog({ title: '彻底删除', message: '记录和它的附件将永久删除，无法恢复。', confirmButtonColor: '#ee0a24' })
  } catch {
    return
  }
  await noteApi.force(n.id)
  list.value = list.value.filter((x) => x.id !== n.id)
  showToast('已彻底删除')
}

onMounted(() => load(true))
</script>

<template>
  <div class="page">
    <PageHeader title="回收站" />
    <div class="desktop-container !pt-0">
      <van-list v-model:loading="loading" :finished="finished" :immediate-check="false" finished-text="" @load="load()">
        <div class="mx-3 mt-2 flex flex-col gap-2">
          <div v-for="n in list" :key="n.id" class="bg-white rounded-xl p-3">
            <div class="flex items-center gap-2 text-xs text-gray-400">
              <span>{{ app.projectName(n.project_id) }}</span>
              <span class="ml-auto">删除于 {{ fmtDate(n.deleted_at) }}</span>
            </div>
            <div class="font-medium mt-1">{{ n.title || '（无标题）' }}</div>
            <div class="text-sm text-gray-500 line-clamp-2 mt-0.5">{{ n.summary }}</div>
            <div class="flex gap-2 mt-2 justify-end">
              <van-button size="small" round plain type="danger" @click="destroy(n)">彻底删除</van-button>
              <van-button size="small" round type="primary" @click="restore(n)">恢复</van-button>
            </div>
          </div>
        </div>
      </van-list>
      <van-empty v-if="finished && !list.length" description="回收站是空的" />
    </div>
  </div>
</template>
