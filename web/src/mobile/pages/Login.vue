<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import { useUserStore } from '@/shared/stores/user'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const username = ref('')
const password = ref('')
const loading = ref(false)

async function submit() {
  if (!username.value.trim() || !password.value) return showToast('请输入用户名和密码')
  loading.value = true
  try {
    await userStore.login(username.value.trim(), password.value)
    router.replace((route.query.redirect as string) || '/')
  } catch {
    /* 拦截器已提示 */
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login">
    <div class="card">
      <img src="/icons/icon.svg" class="w-16 h-16 rounded-2xl mb-3" alt="" />
      <h1 class="text-xl font-semibold m-0">牛马备忘录</h1>
      <p class="text-sm text-gray-400 mt-1 mb-6">项目 · 需求 · 灵感 · 提醒，都在这</p>
      <van-cell-group inset class="!mx-0 w-full">
        <van-field v-model="username" label="账号" placeholder="用户名" autocomplete="username" clearable />
        <van-field v-model="password" type="password" label="密码" placeholder="密码" autocomplete="current-password" @keyup.enter="submit" />
      </van-cell-group>
      <van-button type="primary" block round class="mt-6" :loading="loading" @click="submit">登录</van-button>
      <p class="text-xs text-gray-400 mt-6 m-0">首次使用请在服务器执行 <code>php think user:create</code> 创建账号</p>
    </div>
  </div>
</template>

<style scoped>
.login { min-height: 100dvh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; background: linear-gradient(160deg, #e8f3ff, #f5f6f8 60%); }
.card { width: 100%; max-width: 380px; background: #fff; border-radius: 20px; padding: 32px 24px; display: flex; flex-direction: column; align-items: center; box-shadow: 0 10px 40px rgba(25, 137, 250, 0.1); }
code { background: #f2f3f5; padding: 1px 5px; border-radius: 4px; }
</style>
