<script setup lang="ts">
import { h, onMounted, ref } from 'vue'
import { NButton, type DataTableColumns } from 'naive-ui'
import { noteApi } from '@/shared/api'
import type { NoteListItem } from '@/shared/api/types'
import { useAppStore } from '@/shared/stores/app'
import { fmtDate } from '@/shared/utils/format'
import { feedback } from '@/shared/ui/feedback'

const app = useAppStore()
const list = ref<NoteListItem[]>([])
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    list.value = (await noteApi.trash(1)).list
  } finally {
    loading.value = false
  }
}
async function restore(n: NoteListItem) {
  await noteApi.restore(n.id)
  feedback.success('已恢复')
  load()
  app.fetchProjects()
}
async function destroy(n: NoteListItem) {
  if (!(await feedback.confirm({ title: '彻底删除', message: '记录和它的附件将永久删除，无法恢复。', danger: true, confirmText: '永久删除' }))) return
  await noteApi.force(n.id)
  feedback.success('已彻底删除')
  load()
}
const columns: DataTableColumns<NoteListItem> = [
  { title: '记录', key: 'title', minWidth: 300, render: (n) => h('div', [h('div', { class: 'font-medium' }, n.title || '（无标题）'), h('div', { class: 'tr-sum' }, n.summary)]) },
  { title: '项目', key: 'project_id', width: 140, render: (n) => app.projectName(n.project_id) },
  { title: '删除时间', key: 'deleted_at', width: 160, render: (n) => fmtDate(n.deleted_at) },
  {
    title: '',
    key: 'actions',
    width: 180,
    align: 'right',
    render: (n) => h('div', { class: 'flex justify-end gap-2' }, [
      h(NButton, { size: 'small', type: 'error', quaternary: true, onClick: () => destroy(n) }, () => '彻底删除'),
      h(NButton, { size: 'small', type: 'primary', secondary: true, onClick: () => restore(n) }, () => '恢复'),
    ]),
  },
]
onMounted(load)
</script>

<template>
  <div class="dpage">
    <div class="dpage-inner">
      <div class="dpage-head"><h1>回收站</h1><span class="text-xs" style="color: var(--t-3)">删除的记录会保留在这里，可以恢复或彻底删除</span></div>
      <n-data-table :columns="columns" :data="list" :row-key="(n: NoteListItem) => n.id" :bordered="false" :loading="loading" class="tr-table" />
      <n-empty v-if="!loading && !list.length" class="mt-10" description="回收站是空的" />
    </div>
  </div>
</template>

<style>
.tr-table { border-radius: 12px; overflow: hidden; }
.tr-type { color: #fff; font-size: 11px; padding: 1px 6px; border-radius: 4px; }
.tr-sum { font-size: 12px; color: var(--t-3); margin-top: 2px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>
