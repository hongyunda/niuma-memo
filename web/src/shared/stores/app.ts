import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { isDesktop as isDesktopConst } from '@/shared/platform'
import { projectApi, settingApi, tagApi } from '@/shared/api'
import type { Project, Settings, Tag } from '@/shared/api/types'

export const useAppStore = defineStore('app', () => {
  const isDesktop = ref(isDesktopConst)
  const projects = ref<Project[]>([])
  const tags = ref<Tag[]>([])
  const settings = ref<Settings | null>(null)
  const projectsLoaded = ref(false)

  const activeProjects = computed(() => projects.value.filter((p) => p.status === 1))
  const archivedProjects = computed(() => projects.value.filter((p) => p.status === 2))
  const projectMap = computed(() => Object.fromEntries(projects.value.map((p) => [p.id, p])) as Record<number, Project>)

  async function fetchProjects() {
    const res = await projectApi.list({ status: 'all' })
    projects.value = res.list
    projectsLoaded.value = true
  }

  async function fetchTags() {
    tags.value = (await tagApi.list()).list
  }

  async function fetchSettings(force = false) {
    if (settings.value && !force) return settings.value
    settings.value = await settingApi.get()
    return settings.value
  }

  function projectName(id: number | null | undefined) {
    if (!id) return ''
    return projectMap.value[id]?.name || '未知项目'
  }

  return {
    isDesktop, projects, tags, settings, projectsLoaded,
    activeProjects, archivedProjects, projectMap,
    fetchProjects, fetchTags, fetchSettings, projectName,
  }
})
