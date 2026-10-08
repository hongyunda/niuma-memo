import { onMounted, onUnmounted, ref } from 'vue'
import { dashboardApi, reminderApi } from '@/shared/api'
import type { Dashboard, Reminder } from '@/shared/api/types'
import { feedback } from '@/shared/ui/feedback'

export function useDashboard() {
  const data = ref<Dashboard | null>(null)
  const loading = ref(false)
  const error = ref(false)
  let generation = 0
  async function load() {
    const request = ++generation
    loading.value = true
    error.value = false
    try {
      const result = await dashboardApi.get()
      if (request === generation) data.value = result
    } catch { if (request === generation) error.value = true }
    finally { if (request === generation) loading.value = false }
  }
  async function reminderDone(r: Reminder) {
    await reminderApi.done(r.id)
    feedback.success('已完成')
    await load()
  }
  const events = ['note:created', 'note:saved', 'note:moved']
  onMounted(() => { load(); events.forEach(e => window.addEventListener(e, load)) })
  onUnmounted(() => { generation++; events.forEach(e => window.removeEventListener(e, load)) })
  return { data, loading, error, load, reminderDone }
}
