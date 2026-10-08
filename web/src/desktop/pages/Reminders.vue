<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { reminderApi } from '@/shared/api'
import type { Reminder, ReminderPayload } from '@/shared/api/types'
import { useAppStore } from '@/shared/stores/app'
import { dayjs } from '@/shared/utils/format'
import { feedback } from '@/shared/ui/feedback'
import { pushSupported, currentSubscription, subscribePush } from '@/shared/utils/push'
import ReminderRow from '@/desktop/components/ReminderRow.vue'
import ReminderDialog from '@/desktop/dialogs/ReminderDialog.vue'

const route = useRoute()
const app = useAppStore()
type Scope = 'upcoming' | 'overdue' | 'done'
const scope = ref<Scope>((['upcoming', 'overdue', 'done'].includes(String(route.query.scope)) ? route.query.scope : 'upcoming') as Scope)
const list = ref<Reminder[]>([])
const total = ref(0)
const loading = ref(false)
const showDialog = ref(false)
const editing = ref<Reminder | null>(null)
const calValue = ref(Date.now())
const calDays = ref<Record<string, Reminder[]>>({})
const pushState = ref<'unsupported' | 'unsubscribed' | 'subscribed'>('unsupported')

const selectedDate = computed(() => dayjs(calValue.value).format('YYYY-MM-DD'))
const dayList = computed(() => calDays.value[selectedDate.value] || [])

async function load() {
  loading.value = true
  try {
    const res = await reminderApi.list({ scope: scope.value, limit: 100 })
    list.value = res.list
    total.value = res.total
  } finally {
    loading.value = false
  }
}
async function loadCalendar(ts = calValue.value) {
  const res = await reminderApi.calendar(dayjs(ts).format('YYYY-MM'))
  calDays.value = { ...calDays.value, ...res.days }
}
function refresh() {
  load()
  loadCalendar()
}
async function done(r: Reminder) {
  await reminderApi.done(r.id)
  feedback.success('已完成')
  refresh()
}
async function snooze(r: Reminder, minutes: number) {
  const res = await reminderApi.snooze(r.id, minutes)
  feedback.success(`已推迟到 ${dayjs(res.snooze_until).format('M月D日 HH:mm')}`)
  refresh()
}
async function remove(r: Reminder) {
  if (!(await feedback.confirm({ title: '删除提醒', message: `删除「${r.title}」？`, danger: true, confirmText: '删除' }))) return
  await reminderApi.remove(r.id)
  refresh()
}
async function save(p: ReminderPayload) {
  if (editing.value) await reminderApi.update(editing.value.id, p)
  else await reminderApi.create({ ...p, title: p.title || '提醒' })
  editing.value = null
  feedback.success('已保存')
  refresh()
}
function edit(r: Reminder) {
  editing.value = r
  showDialog.value = true
}
async function checkPush() {
  if (!pushSupported()) return (pushState.value = 'unsupported')
  pushState.value = (await currentSubscription()) ? 'subscribed' : 'unsubscribed'
}
async function enablePush() {
  try {
    await subscribePush((await app.fetchSettings()).vapid_public)
    pushState.value = 'subscribed'
    feedback.success('浏览器通知已开启')
    app.fetchSettings(true)
  } catch (e) {
    feedback.error((e as Error).message)
  }
}
const onPanelChange = (p: { year: number; month: number }) => loadCalendar(new Date(p.year, p.month - 1, 1).getTime())
const marks = (year: number, month: number, date: number) => calDays.value[dayjs(new Date(year, month - 1, date)).format('YYYY-MM-DD')] || []

watch(scope, load)
onMounted(() => {
  refresh()
  checkPush()
})
</script>

<template>
  <div class="dpage">
    <div class="dpage-inner wide">
      <div class="dpage-head">
        <h1>提醒</h1>
        <n-button type="primary" size="small" @click="editing = null; showDialog = true"><template #icon><i class="i-tabler-bell-plus" /></template>新建提醒</n-button>
      </div>
      <n-alert v-if="pushState === 'unsubscribed'" type="info" class="mb-4" :bordered="false">
        开启浏览器通知后，到点会在系统通知栏提醒你。<a class="ml-2 cursor-pointer underline" @click="enablePush">立即开启</a>
      </n-alert>

      <div class="rem-grid">
        <section class="panel">
          <div class="panel-head">
            <div class="seg">
              <button :class="{ on: scope === 'upcoming' }" @click="scope = 'upcoming'">即将</button>
              <button :class="{ on: scope === 'overdue' }" @click="scope = 'overdue'">已过期</button>
              <button :class="{ on: scope === 'done' }" @click="scope = 'done'">已完成</button>
            </div>
            <span class="count">{{ total }} 条</span>
          </div>
          <div v-if="list.length" class="rows"><ReminderRow v-for="r in list" :key="r.id" :reminder="r" :show-actions="scope !== 'done'" @done="done" @snooze="snooze" @edit="edit" @delete="remove" /></div>
          <n-empty v-else-if="!loading" class="py-12" :description="scope === 'upcoming' ? '没有待办提醒' : scope === 'overdue' ? '没有过期的提醒，很棒' : '还没有完成的提醒'" />
        </section>

        <section class="panel">
          <n-calendar v-model:value="calValue" #="{ year, month, date }" @panel-change="onPanelChange">
            <div class="cal-cell">
              <div v-for="r in marks(year, month, date).slice(0, 2)" :key="r.id" class="cal-mark" :class="{ done: r.status >= 3 }">{{ dayjs(r.remind_at).format('HH:mm') }} {{ r.title }}</div>
              <div v-if="marks(year, month, date).length > 2" class="cal-more">+{{ marks(year, month, date).length - 2 }}</div>
            </div>
          </n-calendar>
          <div class="day-head">{{ dayjs(calValue).format('M月D日 dddd') }}</div>
          <div v-if="dayList.length" class="rows"><ReminderRow v-for="r in dayList" :key="r.id" :reminder="r" show-actions :show-date="false" @done="done" @snooze="snooze" @edit="edit" @delete="remove" /></div>
          <p v-else class="empty">这天没有提醒</p>
        </section>
      </div>
    </div>
    <ReminderDialog v-model:show="showDialog" :reminder="editing" @save="save" />
  </div>
</template>

<style scoped>
.wide { max-width: 1400px; }
.rem-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr); gap: 18px; align-items: start; }
@media (max-width: 1200px) { .rem-grid { grid-template-columns: 1fr; } }
.panel { background: #fff; border: 1px solid var(--b-2); border-radius: 14px; padding: 14px 16px; }
.panel-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.count { font-size: 12px; color: var(--t-3); }
.rows { margin: 0 -16px; }
.rows :deep(.rr + .rr) { border-top: 1px solid var(--b-2); }
.cal-cell { display: flex; flex-direction: column; gap: 2px; margin-top: 4px; min-height: 30px; }
.cal-mark { font-size: 11px; line-height: 16px; color: var(--c-primary); background: var(--c-primary-50); border-radius: 4px; padding: 0 5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cal-mark.done { color: var(--t-3); background: var(--bg-muted); text-decoration: line-through; }
.cal-more { font-size: 11px; color: var(--t-3); }
.day-head { font-size: 14px; font-weight: 600; margin: 14px 0 6px; }
.empty { font-size: 13px; color: var(--t-3); margin: 8px 0 0; }
:deep(.n-calendar) { --n-title-font-size: 15px; }
:deep(.n-calendar-cell) { height: 84px; }
</style>
