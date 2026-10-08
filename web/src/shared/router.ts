import { createRouter, createWebHistory, type RouteRecordRaw, type RouteComponent } from 'vue-router'
import { getToken } from '@/shared/utils/request'

export type PageLoader = () => Promise<RouteComponent | { default: RouteComponent }>

/** 两端共用的路由表：路径 / 名称 / 标题一致，页面组件由各端提供 */
export interface PageMap {
  login: PageLoader
  home: PageLoader
  projects: PageLoader
  project: PageLoader
  noteNew: PageLoader
  note: PageLoader
  reminders: PageLoader
  search: PageLoader
  trash: PageLoader
  settings: PageLoader
}

export function createAppRouter(pages: PageMap) {
  const routes: RouteRecordRaw[] = [
    { path: '/login', name: 'login', component: pages.login, meta: { title: '登录', public: true, bare: true } },
    { path: '/', name: 'home', component: pages.home, meta: { title: '今日', tab: 'home' } },
    { path: '/projects', name: 'projects', component: pages.projects, meta: { title: '项目', tab: 'projects' } },
    { path: '/projects/:id(\\d+)', name: 'project', component: pages.project, meta: { title: '项目详情' } },
    { path: '/notes/new', name: 'note-new', component: pages.noteNew, meta: { title: '新建记录' } },
    { path: '/notes/:id(\\d+)', name: 'note', component: pages.note, meta: { title: '记录' } },
    { path: '/reminders', name: 'reminders', component: pages.reminders, meta: { title: '提醒', tab: 'reminders' } },
    { path: '/search', name: 'search', component: pages.search, meta: { title: '搜索' } },
    { path: '/trash', name: 'trash', component: pages.trash, meta: { title: '回收站' } },
    { path: '/settings', name: 'settings', component: pages.settings, meta: { title: '我的', tab: 'settings' } },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ]

  const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: (_to, _from, saved) => saved || { top: 0 },
  })

  router.beforeEach((to) => {
    if (!to.meta.public && !getToken()) {
      return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} }
    }
    if (to.name === 'login' && getToken()) return { name: 'home' }
    document.title = to.meta.title ? `${to.meta.title} · 牛马备忘录` : '牛马备忘录'
    return true
  })

  return router
}
