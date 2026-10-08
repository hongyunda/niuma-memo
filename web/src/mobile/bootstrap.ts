import { createApp } from 'vue'
import { createPinia } from 'pinia'
import 'vant/lib/index.css'
import '@/mobile/styles/mobile.css'
import { showToast, showSuccessToast, showFailToast, showLoadingToast, closeToast, showConfirmDialog } from 'vant'
import { setFeedback } from '@/shared/ui/feedback'
import { createAppRouter } from '@/shared/router'
import MobileApp from './MobileApp.vue'

setFeedback({
  toast: (message, type) => {
    if (type === 'success') showSuccessToast(message)
    else if (type === 'error') showFailToast(message)
    else showToast(message)
  },
  loading: (message) => {
    const t = showLoadingToast({ message, forbidClick: true, duration: 0 })
    return { update: (m) => (t.message = m), close: () => closeToast() }
  },
  confirm: async (o) => {
    try {
      await showConfirmDialog({
        title: o.title,
        message: o.message,
        confirmButtonText: o.confirmText || '确定',
        cancelButtonText: o.cancelText || '取消',
        confirmButtonColor: o.danger ? '#ef4444' : undefined,
      })
      return true
    } catch {
      return false
    }
  },
})

export function mount(selector: string) {
  const app = createApp(MobileApp)
  app.use(createPinia())
  app.use(
    createAppRouter({
      login: () => import('./pages/Login.vue'),
      home: () => import('./pages/Home.vue'),
      projects: () => import('./pages/Projects.vue'),
      project: () => import('./pages/ProjectDetail.vue'),
      noteNew: () => import('./pages/NoteEdit.vue'),
      note: () => import('./pages/NoteEdit.vue'),
      reminders: () => import('./pages/Reminders.vue'),
      search: () => import('./pages/Search.vue'),
      trash: () => import('./pages/Trash.vue'),
      settings: () => import('./pages/Settings.vue'),
    }),
  )
  app.mount(selector)
}
