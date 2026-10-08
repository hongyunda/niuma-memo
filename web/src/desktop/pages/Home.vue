<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { noteApi, reminderApi } from '@/shared/api'
import type { NoteListItem, Reminder, ReminderPayload } from '@/shared/api/types'
import { useDashboard } from '@/shared/composables/useDashboard'
import { feedback } from '@/shared/ui/feedback'
import NoteRow from '@/desktop/components/NoteRow.vue'
import ReminderRow from '@/desktop/components/ReminderRow.vue'
import ReminderDialog from '@/desktop/dialogs/ReminderDialog.vue'

const router = useRouter()
const { data, error, load, reminderDone } = useDashboard()
const query = ref('')
const searchInput = ref<HTMLInputElement>()
const editingReminder = ref<Reminder | null>(null)
const showReminder = ref(false)
function search() {
  const q = query.value.trim()
  if (q) router.push({ name: 'search', query: { q } })
}
async function onRowAction(key: string, n: NoteListItem) {
  if (key === 'move') return router.push(`/notes/${n.id}`)
  if (key === 'pin') await noteApi.pin(n.id, n.is_pinned ? 0 : 1)
  else if (key === 'archive') await noteApi.archive(n.id, n.is_archived ? 0 : 1)
  else if (key === 'delete') {
    if (!(await feedback.confirm({ title: '删除记录', message: '会进入回收站，可以恢复。', danger: true, confirmText: '删除' }))) return
    await noteApi.remove(n.id)
  }
  load()
}
async function snooze(r: Reminder, minutes: number) { await reminderApi.snooze(r.id, minutes); load() }
async function removeReminder(r: Reminder) {
  if (!(await feedback.confirm({ title: '删除提醒', message: `删除「${r.title}」？`, danger: true, confirmText: '删除' }))) return
  await reminderApi.remove(r.id)
  load()
}
function editReminder(r: Reminder) { editingReminder.value = r; showReminder.value = true }
async function saveReminder(p: ReminderPayload) {
  if (editingReminder.value) await reminderApi.update(editingReminder.value.id, p)
  editingReminder.value = null
  load()
}
function onKeydown(e: KeyboardEvent) {
  const el = document.activeElement as HTMLElement | null
  if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(el?.tagName || '') && !el?.isContentEditable) {
    e.preventDefault()
    searchInput.value?.focus()
  }
}
onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div class="dpage">
    <div class="home">
      <form class="home-search" role="search" @submit.prevent="search">
        <i class="i-tabler-search" />
        <input ref="searchInput" v-model="query" aria-label="搜索记录和文件" placeholder="搜索记录、文件、链接…" />
        <n-button type="primary" attr-type="submit" :disabled="!query.trim()">搜索</n-button>
      </form>
      <div v-if="data" class="home-grid">
        <section class="home-section">
          <h2>今日提醒</h2>
          <div v-if="data.today_reminders.length" class="home-reminders">
            <ReminderRow v-for="r in data.today_reminders" :key="r.id" :reminder="r" show-actions :show-date="false" @done="reminderDone" @snooze="snooze" @edit="editReminder" @delete="removeReminder" />
          </div>
          <p v-else class="empty">今天没有提醒</p>
        </section>
        <section class="home-section">
          <h2>最近编辑</h2>
          <div v-if="data.recent_notes.length">
            <NoteRow v-for="n in data.recent_notes" :key="n.id" :note="n" show-project compact @select="router.push(`/notes/${n.id}`)" @action="onRowAction" />
          </div>
          <p v-else class="empty">还没有记录，在项目里新建一条吧</p>
        </section>
      </div>
      <div v-else-if="error" class="empty">加载失败 <n-button text type="primary" @click="load">重试</n-button></div>
      <n-skeleton v-else text :repeat="6" />
    </div>
    <ReminderDialog v-model:show="showReminder" :reminder="editingReminder" @save="saveReminder" />
  </div>
</template>

<style scoped>
.home { width: 95%; max-width: 1360px; margin: 0 auto; padding: 40px 12px; }
.home-search { display: flex; align-items: center; gap: 16px; width: 80%; max-width: 900px; min-height: 64px; margin: 0 auto 44px; padding: 10px 12px 10px 22px; background: #fff; border: 1px solid var(--b-1); border-radius: 12px; }
.home-search:focus-within { border-color: var(--c-primary); }
.home-search > i { font-size: 24px; color: var(--t-2); flex: 0 0 auto; }
.home-search input { flex: 1; min-width: 0; border: 0; outline: none; background: transparent; font: inherit; font-size: 18px; color: var(--t-1); }
.home-search input::placeholder { color: var(--t-2); }
.home-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 3fr); gap: 32px; }
.home-section { min-width: 0; background: #fff; border-radius: 12px; padding: 18px 20px; }
.home-section h2 { margin: 0 0 16px; font-size: 17px; font-weight: 600; }
.home-reminders :deep(.rr + .rr) { border-top: 1px solid var(--b-2); }
.home-section :deep(.row) { padding-left: 0; padding-right: 0; }
.home-section :deep(.row:last-child) { border-bottom: 0; }
.empty { margin: 0; padding: 14px 0; color: var(--t-2); font-size: 14px; }
@media (max-width: 1100px) { .home-grid { grid-template-columns: 1fr; } .home-search { width: 100%; } }
</style>
