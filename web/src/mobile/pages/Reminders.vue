<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { showConfirmDialog, showToast, type CalendarDayItem } from 'vant'
import { reminderApi } from '@/shared/api'
import type { Reminder, ReminderPayload } from '@/shared/api/types'
import { useAppStore } from '@/shared/stores/app'
import { dayjs } from '@/shared/utils/format'
import { isIOS, isStandalone, pushSupported, currentSubscription, subscribePush } from '@/shared/utils/push'
import PageHeader from '@/mobile/components/PageHeader.vue'
import ReminderItem from '@/mobile/components/ReminderItem.vue'
import ReminderPicker from '@/mobile/components/ReminderPicker.vue'
import SnoozeSheet from '@/mobile/components/SnoozeSheet.vue'

const app = useAppStore()
const route = useRoute()
const tab = ref<'upcoming' | 'overdue' | 'done' | 'calendar'>((['upcoming', 'overdue', 'done', 'calendar'].includes(String(route.query.scope)) ? route.query.scope : 'upcoming') as 'upcoming' | 'overdue' | 'done' | 'calendar')
const list = ref<Reminder[]>([])
const loading = ref(false)
const finished = ref(false)
const page = ref(1)
const showPicker = ref(false)
const editing = ref<Reminder | null>(null)
const snoozeTarget = ref<Reminder | null>(null)

const calendarDays = ref<Record<string, Reminder[]>>({})
const selectedDate = ref(dayjs().format('YYYY-MM-DD'))
const pushState = ref<'unsupported' | 'unsubscribed' | 'subscribed' | 'ios-browser'>('unsupported')

async function load(reset = false) {
  if (tab.value === 'calendar') return
  if (reset) {
    page.value = 1
    list.value = []
    finished.value = false
  }
  loading.value = true
  try {
    const res = await reminderApi.list({ scope: tab.value, page: page.value, limit: 30 })
    list.value.push(...res.list)
    finished.value = page.value >= res.last_page
    page.value++
  } finally {
    loading.value = false
  }
}

async function loadCalendar(date = selectedDate.value) {
  const res = await reminderApi.calendar(dayjs(date).format('YYYY-MM'))
  calendarDays.value = { ...calendarDays.value, ...res.days }
}

const dayList = computed(() => calendarDays.value[selectedDate.value] || [])

function onSelectDay(d: Date) {
  selectedDate.value = dayjs(d).format('YYYY-MM-DD')
}
function onPanelChange(p: { date: Date }) {
  loadCalendar(dayjs(p.date).format('YYYY-MM-DD'))
}

function formatter(day: CalendarDayItem) {
  if (!day.date) return day
  const key = dayjs(day.date).format('YYYY-MM-DD')
  const n = calendarDays.value[key]?.length
  if (n) day.bottomInfo = n > 1 ? `${n} 条` : '•'
  return day
}

async function done(r: Reminder) {
  await reminderApi.done(r.id)
  showToast({ message: '已完成', icon: 'success' })
  refresh()
}

async function remove(r: Reminder) {
  try {
    await showConfirmDialog({ title: '删除提醒', message: `删除「${r.title}」？` })
  } catch {
    return
  }
  await reminderApi.remove(r.id)
  refresh()
}

function edit(r: Reminder) {
  editing.value = r
  showPicker.value = true
}

async function savePicker(p: ReminderPayload) {
  if (editing.value) await reminderApi.update(editing.value.id, p)
  else await reminderApi.create({ ...p, title: p.title || '提醒' })
  editing.value = null
  showToast({ message: '已保存', icon: 'success' })
  refresh()
}

function refresh() {
  load(true)
  if (tab.value === 'calendar') loadCalendar()
}

async function checkPush() {
  if (!pushSupported()) {
    pushState.value = isIOS() && !isStandalone() ? 'ios-browser' : 'unsupported'
    return
  }
  pushState.value = (await currentSubscription()) ? 'subscribed' : 'unsubscribed'
}

async function enablePush() {
  try {
    const settings = await app.fetchSettings()
    await subscribePush(settings.vapid_public)
    pushState.value = 'subscribed'
    showToast({ message: '浏览器通知已开启', icon: 'success' })
    app.fetchSettings(true)
  } catch (e) {
    showToast((e as Error).message)
  }
}

watch(tab, (t) => (t === 'calendar' ? loadCalendar() : load(true)))
onMounted(() => {
  load(true)
  checkPush()
})
</script>

<template>
  <div class="page">
    <PageHeader title="提醒" :back="false">
      <template #right><van-icon name="plus" size="20" class="cursor-pointer" @click="editing = null; showPicker = true" /></template>
    </PageHeader>

    <div class="desktop-container !pt-0">
      <van-notice-bar v-if="pushState === 'unsubscribed'" left-icon="bell" mode="closeable" color="#1989fa" background="#e8f3ff" class="mx-3 mt-2 rounded-lg">
        开启浏览器通知，到点才能在锁屏收到提醒。<span class="underline cursor-pointer ml-1" @click="enablePush">立即开启</span>
      </van-notice-bar>
      <van-notice-bar v-else-if="pushState === 'ios-browser'" left-icon="info-o" mode="closeable" wrapable class="mx-3 mt-2 rounded-lg">
        iPhone 请先用 Safari「添加到主屏幕」，从桌面图标打开后才能收到通知；或在「我的」里配置 Bark。
      </van-notice-bar>

      <van-tabs v-model:active="tab" sticky :offset-top="46" line-width="20">
        <van-tab title="即将" name="upcoming" />
        <van-tab title="已过期" name="overdue" />
        <van-tab title="已完成" name="done" />
        <van-tab title="日历" name="calendar" />
      </van-tabs>

      <template v-if="tab !== 'calendar'">
        <van-list v-model:loading="loading" :finished="finished" :immediate-check="false" finished-text="" @load="load()">
          <div class="mx-3 mt-2 bg-white rounded-xl overflow-hidden divide-y divide-gray-100">
            <ReminderItem v-for="r in list" :key="r.id" :reminder="r" :show-actions="tab !== 'done'" @done="done" @snooze="snoozeTarget = $event" @edit="edit" @delete="remove" />
          </div>
        </van-list>
        <van-empty v-if="finished && !list.length" :description="tab === 'upcoming' ? '没有待办提醒' : tab === 'overdue' ? '没有过期的提醒' : '还没有完成的提醒'" />
      </template>

      <template v-else>
        <div class="mx-3 mt-2 bg-white rounded-xl overflow-hidden">
          <van-calendar
            :poppable="false"
            :show-title="false"
            :show-confirm="false"
            :min-date="new Date(new Date().getFullYear() - 1, 0, 1)"
            :max-date="new Date(new Date().getFullYear() + 2, 11, 31)"
            :default-date="new Date()"
            :formatter="formatter"
            :row-height="52"
            switch-mode="month"
            style="height: 420px"
            @select="onSelectDay"
            @panel-change="onPanelChange"
          />
        </div>
        <div class="mx-3 mt-2 text-sm font-medium px-1">{{ dayjs(selectedDate).format('M月D日 dddd') }}</div>
        <div class="mx-3 mt-2 bg-white rounded-xl overflow-hidden divide-y divide-gray-100">
          <ReminderItem v-for="r in dayList" :key="r.id" :reminder="r" show-actions @done="done" @snooze="snoozeTarget = $event" @edit="edit" @delete="remove" />
          <div v-if="!dayList.length" class="text-center text-gray-400 text-sm py-6">这天没有提醒</div>
        </div>
      </template>
    </div>

    <ReminderPicker v-model:show="showPicker" :reminder="editing" @save="savePicker" />
    <SnoozeSheet :reminder="snoozeTarget" @close="snoozeTarget = null" @snoozed="snoozeTarget = null; refresh()" />
  </div>
</template>
