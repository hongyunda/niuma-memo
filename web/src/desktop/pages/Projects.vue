<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { projectApi } from '@/shared/api'
import type { Project } from '@/shared/api/types'
import { useAppStore } from '@/shared/stores/app'
import { smartTime } from '@/shared/utils/format'
import { feedback } from '@/shared/ui/feedback'
import ProjectDialog from '@/desktop/dialogs/ProjectDialog.vue'
const router = useRouter()
const app = useAppStore()
const keyword = ref('')
const archived = ref(false)
const showForm = ref(false)
const editing = ref<Project | null>(null)
const rows = computed(() => app.projects.filter(p => p.status === (archived.value ? 2 : 1) && (!keyword.value || p.name.includes(keyword.value) || p.description?.includes(keyword.value))))
function edit(p: Project | null) { editing.value = p; showForm.value = true }
async function archive(p: Project) { await projectApi.update(p.id, { status: p.status === 2 ? 1 : 2 }); app.fetchProjects() }
async function remove(p: Project) {
  if (!(await feedback.confirm({ title: `删除「${p.name}」`, message: p.note_count ? '记录会保留，可从最近编辑和搜索打开。' : '确定删除该项目？', danger: true, confirmText: '删除' }))) return
  await projectApi.remove(p.id); app.fetchProjects()
}
onMounted(() => app.fetchProjects())
</script>
<template>
  <div class="dpage">
    <header class="dp-bar"><h1>项目</h1><n-button type="primary" @click="edit(null)"><template #icon><i class="i-tabler-plus" /></template>新建项目</n-button></header>
    <div class="projects">
      <div class="project-tools"><n-input v-model:value="keyword" placeholder="搜索项目" clearable /><n-switch v-model:value="archived" /><span>已归档</span></div>
      <div v-for="p in rows" :key="p.id" class="project-row">
        <button class="project-open" @click="router.push(`/projects/${p.id}`)"><strong>{{ p.name }}</strong><span v-if="p.description">{{ p.description }}</span></button>
        <span class="project-meta">{{ p.note_count || 0 }} 条记录 · {{ smartTime(p.last_note_at || p.updated_at) }}</span>
        <div class="project-actions">
          <n-button size="small" quaternary @click="edit(p)">编辑</n-button>
          <n-button size="small" quaternary @click="archive(p)">{{ p.status === 2 ? '恢复' : '归档' }}</n-button>
          <n-button size="small" quaternary type="error" @click="remove(p)">删除</n-button>
        </div>
      </div>
      <n-empty v-if="app.projectsLoaded && !rows.length" class="mt-8" description="还没有项目" />
    </div>
    <ProjectDialog v-model:show="showForm" :project="editing" @saved="app.fetchProjects()" />
  </div>
</template>
<style scoped>
.projects { padding: 24px 32px; }
.project-tools { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; font-size: 13px; }
.project-tools .n-input { max-width: 320px; }
.project-row { display: flex; align-items: center; gap: 20px; padding: 16px 0; border-bottom: 1px solid var(--b-1); }
.project-open { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 6px; padding: 0; border: 0; background: transparent; text-align: left; color: var(--t-1); cursor: pointer; }
.project-open strong { font-size: 16px; font-weight: 600; }
.project-open span { font-size: 13px; color: var(--t-2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.project-meta { color: var(--t-2); font-size: 12px; white-space: nowrap; }
.project-actions { display: flex; gap: 4px; }
</style>
