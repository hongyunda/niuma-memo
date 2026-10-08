<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboard } from '@/shared/composables/useDashboard'
import type { Reminder } from '@/shared/api/types'
import PageHeader from '@/mobile/components/PageHeader.vue'
import NoteCard from '@/mobile/components/NoteCard.vue'
import ReminderItem from '@/mobile/components/ReminderItem.vue'
import SnoozeSheet from '@/mobile/components/SnoozeSheet.vue'
const router = useRouter()
const { data, error, load, reminderDone } = useDashboard()
const query = ref('')
const refreshing = ref(false)
const snoozeTarget = ref<Reminder | null>(null)
function search() { const q = query.value.trim(); if (q) router.push({ name: 'search', query: { q } }) }
async function refresh() { await load(); refreshing.value = false }
</script>
<template>
  <div class="page">
    <PageHeader title="工作台" :back="false" />
    <van-pull-refresh v-model="refreshing" @refresh="refresh">
      <div class="home">
        <form class="home-search" role="search" @submit.prevent="search">
          <van-icon name="search" />
          <input v-model="query" aria-label="搜索记录和文件" placeholder="搜索记录、文件、链接…" />
          <button type="submit" :disabled="!query.trim()">搜索</button>
        </form>
        <template v-if="data">
          <section class="home-section">
            <h2>今日提醒</h2>
            <ReminderItem v-for="r in data.today_reminders" :key="r.id" :reminder="r" show-actions @done="reminderDone" @snooze="snoozeTarget = $event" @edit="router.push('/reminders')" @delete="router.push('/reminders')" />
            <p v-if="!data.today_reminders.length" class="empty">今天没有提醒</p>
          </section>
          <section class="home-section">
            <h2>最近编辑</h2>
            <NoteCard v-for="n in data.recent_notes" :key="n.id" :note="n" show-project />
            <p v-if="!data.recent_notes.length" class="empty">还没有记录，在项目里新建一条吧</p>
          </section>
        </template>
        <van-button v-else-if="error" block @click="load">加载失败，点击重试</van-button>
        <van-skeleton v-else title :row="6" />
      </div>
    </van-pull-refresh>
    <SnoozeSheet :reminder="snoozeTarget" @close="snoozeTarget = null" @snoozed="snoozeTarget = null; load()" />
  </div>
</template>
<style scoped>
.home { padding: 20px 12px; }
.home-search { display: flex; align-items: center; gap: 10px; min-height: 56px; padding: 8px 12px; background: #fff; border: 1px solid var(--b-1); border-radius: 12px; margin-bottom: 24px; }
.home-search:focus-within { border-color: var(--c-primary); }
.home-search > .van-icon { font-size: 22px; }
.home-search input { flex: 1; min-width: 0; border: 0; outline: none; background: transparent; font: inherit; font-size: 15px; }
.home-search button { border: 0; background: transparent; color: var(--c-primary); font-size: 14px; }
.home-search button:disabled { color: var(--t-3); }
.home-section { background: #fff; border-radius: 12px; padding: 14px 12px; margin-bottom: 18px; }
.home-section h2 { margin: 0 0 14px; font-size: 16px; }
.empty { padding: 10px 0; margin: 0; color: var(--t-2); font-size: 13px; }
</style>
