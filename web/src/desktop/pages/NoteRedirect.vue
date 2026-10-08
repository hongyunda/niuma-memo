<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { noteApi } from '@/shared/api'
import { useAppStore } from '@/shared/stores/app'
import Workspace from '@/desktop/components/Workspace.vue'
const route = useRoute()
const router = useRouter()
const app = useAppStore()
const standalone = ref(false)
onMounted(async () => {
  if (route.name === 'note') {
    try {
      const n = await noteApi.get(Number(route.params.id))
      if (n.project_id) router.replace({ name: 'project', params: { id: n.project_id }, query: { note: n.id } })
      else standalone.value = true
    } catch { router.replace('/') }
  } else {
    if (!app.projectsLoaded) await app.fetchProjects()
    const pid = route.query.project_id ? Number(route.query.project_id) : app.activeProjects[0]?.id
    if (pid) router.replace({ name: 'project', params: { id: pid }, query: { note: 'new' } })
    else standalone.value = true
  }
})
</script>
<template><Workspace v-if="standalone" mode="record" /><div v-else class="flex items-center justify-center h-full"><n-spin size="small" /></div></template>
