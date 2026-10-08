<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAppStore } from '@/shared/stores/app'
import PageHeader from '@/mobile/components/PageHeader.vue'
import ProjectForm from '@/mobile/components/ProjectForm.vue'
const router = useRouter()
const app = useAppStore()
const keyword = ref('')
const archived = ref(false)
const showForm = ref(false)
const rows = computed(() => app.projects.filter(p => p.status === (archived.value ? 2 : 1) && (!keyword.value || p.name.includes(keyword.value))))
onMounted(() => app.fetchProjects())
</script>
<template>
  <div class="page">
    <PageHeader title="项目" :back="false"><template #right><van-icon name="plus" size="22" aria-label="新建项目" @click="showForm = true" /></template></PageHeader>
    <van-search v-model="keyword" placeholder="搜索项目" />
    <van-cell title="已归档"><template #right-icon><van-switch v-model="archived" size="22" /></template></van-cell>
    <van-cell v-for="p in rows" :key="p.id" :title="p.name" :label="p.description || ((p.note_count || 0) + ' 条记录')" is-link @click="router.push(`/projects/${p.id}`)" />
    <van-empty v-if="app.projectsLoaded && !rows.length" description="还没有项目" />
    <ProjectForm v-model:show="showForm" @saved="app.fetchProjects()" />
  </div>
</template>
