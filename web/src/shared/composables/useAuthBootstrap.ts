import { onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '@/shared/stores/app'
import { useUserStore } from '@/shared/stores/user'

/** 登录态引导：拉用户 / 项目 / 标签，token 失效时回登录页 */
export function useAuthBootstrap() {
  const router = useRouter()
  const route = useRoute()
  const userStore = useUserStore()
  const app = useAppStore()

  function bootstrap() {
    if (!userStore.isLoggedIn()) return
    userStore.fetchMe()
    app.fetchProjects()
    app.fetchTags()
  }

  onMounted(() => {
    window.addEventListener('auth:expired', () => {
      userStore.clear()
      if (route.name !== 'login') router.replace({ name: 'login', query: { redirect: route.fullPath } })
    })
    bootstrap()
  })

  watch(() => userStore.token, (token, old) => {
    if (token && !old) bootstrap()
  })
}
