<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/shared/stores/user'
import { feedback } from '@/shared/ui/feedback'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const username = ref('')
const password = ref('')
const loading = ref(false)

async function submit() {
  if (!username.value.trim() || !password.value) return feedback.error('请输入用户名和密码')
  loading.value = true
  try {
    await userStore.login(username.value.trim(), password.value)
    router.replace((route.query.redirect as string) || '/')
  } catch {
    /* 已提示 */
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login">
    <div class="card">
      <img src="/icons/icon.svg" alt="" />
      <h1>牛马备忘录</h1>
      <p>项目 · 需求 · 灵感 · 提醒，都在这</p>
      <n-input v-model:value="username" size="large" placeholder="用户名" autofocus @keydown.enter="submit"><template #prefix><i class="i-tabler-user" /></template></n-input>
      <n-input v-model:value="password" size="large" type="password" show-password-on="click" placeholder="密码" @keydown.enter="submit"><template #prefix><i class="i-tabler-lock" /></template></n-input>
      <n-button type="primary" size="large" block :loading="loading" @click="submit">登录</n-button>
      <small>首次使用请在服务器执行 <code>php think user:create</code> 创建账号</small>
    </div>
  </div>
</template>

<style scoped>
.login { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: radial-gradient(1200px 600px at 20% 0%, #e8f1ff, transparent), var(--bg-page); }
.card { width: 380px; background: #fff; border-radius: 20px; padding: 36px 32px 28px; display: flex; flex-direction: column; gap: 14px; align-items: stretch; box-shadow: 0 20px 60px rgba(43, 127, 255, 0.12); text-align: center; }
.card img { width: 60px; height: 60px; border-radius: 16px; margin: 0 auto; }
.card h1 { margin: 6px 0 0; font-size: 22px; font-weight: 600; }
.card p { margin: -8px 0 8px; font-size: 13px; color: var(--t-3); }
.card small { font-size: 12px; color: var(--t-3); margin-top: 6px; }
code { background: var(--bg-muted); padding: 1px 5px; border-radius: 4px; }
</style>
