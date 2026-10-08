<script setup lang="ts">
import { computed, h, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { noteApi } from '@/shared/api'
import { useAppStore } from '@/shared/stores/app'
import { useUserStore } from '@/shared/stores/user'
import { feedback } from '@/shared/ui/feedback'
import { modKey } from '@/shared/platform'
import ProjectDialog from '@/desktop/dialogs/ProjectDialog.vue'

const emit = defineEmits<{ (e: 'search'): void }>()
const app = useAppStore()
const userStore = useUserStore()
const router = useRouter()
const route = useRoute()
const showArchived = ref(false)
const showProject = ref(false)
const dragPid = ref<number | null | undefined>(undefined)

const isActive = (name: string, id?: number) => route.name === name && (id === undefined || Number(route.params.id) === id)

const nav = [
  { name: 'home', to: '/', icon: 'i-tabler-layout-dashboard', label: '工作台' },
  { name: 'reminders', to: '/reminders', icon: 'i-tabler-bell', label: '提醒' },
]

const userMenu = computed(() => [
  { label: '设置与推送', key: 'settings', icon: () => h('i', { class: 'i-tabler-settings' }) },
  { label: '项目管理', key: 'projects', icon: () => h('i', { class: 'i-tabler-folders' }) },
  { label: '回收站', key: 'trash', icon: () => h('i', { class: 'i-tabler-trash' }) },
  { type: 'divider', key: 'd1' },
  { label: '退出登录', key: 'logout', icon: () => h('i', { class: 'i-tabler-logout' }) },
])

async function onUserMenu(key: string) {
  if (key === 'logout') {
    if (!(await feedback.confirm({ title: '退出登录', message: '确定退出当前账号？' }))) return
    await userStore.logout()
    router.replace('/login')
  } else router.push(`/${key}`)
}

async function onDropNote(e: DragEvent, pid: number) {
  dragPid.value = undefined
  const id = Number(e.dataTransfer?.getData('text/note-id'))
  if (!id) return
  await noteApi.move([id], pid)
  feedback.success(`已归入 ${app.projectName(pid)}`)
  app.fetchProjects()
  window.dispatchEvent(new CustomEvent('note:moved', { detail: { id, project_id: pid } }))
}
</script>

<template>
  <aside class="sb">
    <div class="sb-brand" @click="router.push('/')">
      <img src="/icons/icon.svg" alt="" />
      <span>牛马备忘录</span>
    </div>

    <button class="sb-search" @click="emit('search')">
      <i class="i-tabler-search" />
      <span>搜索 / 命令</span>
      <kbd>{{ modKey() }}K</kbd>
    </button>

    <nav class="sb-nav">
      <a
        v-for="n in nav"
        :key="n.name"
        class="sb-item"
        :class="{ active: isActive(n.name) }"
        @click="router.push(n.to)"
      >
        <i :class="n.icon" />
        <span class="flex-1">{{ n.label }}</span>
      </a>
    </nav>

    <div class="sb-section">
      <span>项目</span>
      <n-tooltip trigger="hover" placement="right">
        <template #trigger><button class="sb-plus" @click="showProject = true"><i class="i-tabler-plus" /></button></template>
        新建项目
      </n-tooltip>
    </div>
    <nav class="sb-nav sb-projects">
      <a
        v-for="p in app.activeProjects"
        :key="p.id"
        class="sb-item"
        :class="{ active: isActive('project', p.id), drop: dragPid === p.id }"
        :title="p.name"
        @click="router.push(`/projects/${p.id}`)"
        @dragover.prevent="dragPid = p.id"
        @dragleave="dragPid = undefined"
        @drop.prevent="onDropNote($event, p.id)"
      >
        <span class="sb-dot" style="background: var(--c-primary)" />
        <span class="flex-1 text-ellipsis">{{ p.name }}</span>
      </a>
      <div v-if="app.projectsLoaded && !app.activeProjects.length" class="sb-empty">还没有项目<br /><a @click="showProject = true">创建第一个 →</a></div>
      <a v-if="app.archivedProjects.length" class="sb-item muted" @click="showArchived = !showArchived">
        <i :class="showArchived ? 'i-tabler-chevron-down' : 'i-tabler-chevron-right'" />
        <span class="flex-1">已归档 {{ app.archivedProjects.length }}</span>
      </a>
      <template v-if="showArchived">
        <a v-for="p in app.archivedProjects" :key="p.id" class="sb-item muted" :class="{ active: isActive('project', p.id) }" @click="router.push(`/projects/${p.id}`)">
          <span class="sb-dot" style="background: #c9cdd4" />
          <span class="flex-1 text-ellipsis">{{ p.name }}</span>
        </a>
      </template>
    </nav>

    <div class="sb-footer">
      <n-dropdown :options="userMenu" trigger="click" placement="top-start" @select="onUserMenu">
        <button class="sb-user">
          <n-avatar round :size="30" color="#2b7fff">{{ (userStore.user?.nickname || userStore.user?.username || '?').slice(0, 1) }}</n-avatar>
          <span class="flex-1 text-ellipsis text-left">{{ userStore.user?.nickname || userStore.user?.username }}</span>
          <i class="i-tabler-dots" />
        </button>
      </n-dropdown>
    </div>

    <ProjectDialog v-model:show="showProject" @saved="app.fetchProjects()" />
  </aside>
</template>

<style scoped>
.sb { width: var(--memo-sidebar-w); flex: 0 0 var(--memo-sidebar-w); height: 100vh; display: flex; flex-direction: column; background: #fbfbfc; border-right: 1px solid var(--b-1); padding: 14px 10px 10px; box-sizing: border-box; }
.sb-brand { display: flex; align-items: center; gap: 9px; padding: 4px 8px 12px; font-weight: 600; font-size: 15px; cursor: pointer; color: var(--t-1); }
.sb-brand img { width: 26px; height: 26px; border-radius: 7px; }
.sb-search { display: flex; align-items: center; gap: 8px; width: 100%; height: 34px; padding: 0 10px; border: 1px solid var(--b-1); border-radius: 9px; background: #fff; color: var(--t-3); font-size: 13px; cursor: pointer; margin-bottom: 10px; transition: border-color var(--dur), box-shadow var(--dur); }
.sb-search:hover { border-color: #c9cdd4; }
.sb-search span { flex: 1; text-align: left; }
.sb-search kbd { font-family: inherit; font-size: 11px; color: var(--t-3); background: var(--bg-muted); border-radius: 4px; padding: 1px 5px; }
.sb-nav { display: flex; flex-direction: column; gap: 2px; }
.sb-projects { flex: 1 1 auto; overflow-y: auto; min-height: 0; }
.sb-item { display: flex; align-items: center; gap: 9px; height: 34px; padding: 0 10px; border-radius: 8px; font-size: 13.5px; color: var(--t-1); cursor: pointer; user-select: none; transition: background var(--dur), color var(--dur); }
.sb-item i { font-size: 17px; color: var(--t-2); flex: 0 0 auto; }
.sb-item:hover { background: #f0f2f5; }
.sb-item.active { background: var(--c-primary-50); color: var(--c-primary); font-weight: 500; }
.sb-item.active i { color: var(--c-primary); }
.sb-item.muted { color: var(--t-3); }
.sb-item.drop { background: #e3f0ff; box-shadow: inset 0 0 0 2px var(--c-primary); }
.sb-dot { width: 7px; height: 7px; border-radius: 50%; flex: 0 0 auto; margin: 0 5px; opacity: 0.85; }
.sb-count { font-size: 11px; color: var(--t-2); background: var(--bg-muted); border-radius: 10px; padding: 0 7px; line-height: 18px; min-width: 18px; text-align: center; }
.sb-count.warn { color: #b45309; background: #fff4e0; }
.sb-section { display: flex; align-items: center; justify-content: space-between; padding: 16px 10px 6px; font-size: 12px; color: var(--t-3); }
.sb-plus { width: 22px; height: 22px; border: 0; border-radius: 6px; background: transparent; color: var(--t-3); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 15px; }
.sb-plus:hover { background: var(--bg-muted); color: var(--c-primary); }
.sb-empty { font-size: 12px; color: var(--t-3); padding: 8px 12px; line-height: 1.7; }
.sb-empty a { color: var(--c-primary); cursor: pointer; }
.sb-footer { border-top: 1px solid var(--b-2); padding-top: 8px; margin-top: 8px; }
.sb-user { display: flex; align-items: center; gap: 9px; width: 100%; height: 42px; padding: 0 8px; border: 0; border-radius: 9px; background: transparent; cursor: pointer; font-size: 13.5px; color: var(--t-1); font-family: inherit; }
.sb-user:hover { background: #f0f2f5; }
.sb-user i { color: var(--t-3); }
</style>
