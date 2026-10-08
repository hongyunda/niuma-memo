<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const bare = computed(() => !!route.meta.bare)
const activeTab = computed(() => (route.meta.tab as string) || '')
</script>

<template>
  <template v-if="bare"><slot /></template>
  <div v-else class="mobile-shell">
    <slot />
    <van-tabbar v-if="activeTab" :model-value="activeTab" route safe-area-inset-bottom fixed placeholder>
      <van-tabbar-item name="home" to="/" icon="wap-home-o">今日</van-tabbar-item>
      <van-tabbar-item name="projects" to="/projects" icon="apps-o">项目</van-tabbar-item>
      <van-tabbar-item name="plus" @click="router.push('/notes/new')">
        <template #icon><div class="tab-plus"><van-icon name="plus" /></div></template>
        记录
      </van-tabbar-item>
      <van-tabbar-item name="reminders" to="/reminders" icon="bell">提醒</van-tabbar-item>
      <van-tabbar-item name="settings" to="/settings" icon="user-o">我的</van-tabbar-item>
    </van-tabbar>
  </div>
</template>

<style scoped>
.tab-plus {
  width: 42px; height: 42px; margin-top: -14px; border-radius: 50%;
  background: linear-gradient(160deg, #4d93ff, #2b7fff); color: #fff;
  display: flex; align-items: center; justify-content: center; font-size: 22px;
  box-shadow: 0 6px 14px rgba(43, 127, 255, 0.35);
}
</style>
