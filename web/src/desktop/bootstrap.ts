import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createDiscreteApi, zhCN, dateZhCN } from 'naive-ui'
import '@/desktop/styles/desktop.css'
import { setFeedback } from '@/shared/ui/feedback'
import { createAppRouter } from '@/shared/router'
import { themeOverrides } from './theme'
import DesktopApp from './DesktopApp.vue'

const { message, dialog } = createDiscreteApi(['message', 'dialog'], {
  configProviderProps: { themeOverrides, locale: zhCN, dateLocale: dateZhCN },
  messageProviderProps: { placement: 'bottom', max: 3 },
})

setFeedback({
  toast: (content, type = 'info') => {
    if (type === 'success') message.success(content)
    else if (type === 'error') message.error(content)
    else if (type === 'warning') message.warning(content)
    else message.info(content)
  },
  loading: (content) => {
    const m = message.loading(content, { duration: 0 })
    return { update: (c) => (m.content = c), close: () => m.destroy() }
  },
  confirm: (o) =>
    new Promise<boolean>((resolve) => {
      dialog.create({
        type: o.danger ? 'warning' : 'info',
        title: o.title,
        content: o.message,
        positiveText: o.confirmText || '确定',
        negativeText: o.cancelText || '取消',
        positiveButtonProps: o.danger ? { type: 'error' } : undefined,
        autoFocus: false,
        onPositiveClick: () => resolve(true),
        onNegativeClick: () => resolve(false),
        onClose: () => resolve(false),
        onMaskClick: () => resolve(false),
        onEsc: () => resolve(false),
      })
    }),
})

export function mount(selector: string) {
  const app = createApp(DesktopApp)
  app.use(createPinia())
  app.use(
    createAppRouter({
      login: () => import('./pages/Login.vue'),
      home: () => import('./pages/Home.vue'),
      projects: () => import('./pages/Projects.vue'),
      project: () => import('./pages/ProjectDetail.vue'),
      noteNew: () => import('./pages/NoteRedirect.vue'),
      note: () => import('./pages/NoteRedirect.vue'),
      reminders: () => import('./pages/Reminders.vue'),
      search: () => import('./pages/Search.vue'),
      trash: () => import('./pages/Trash.vue'),
      settings: () => import('./pages/Settings.vue'),
    }),
  )
  app.mount(selector)
}
