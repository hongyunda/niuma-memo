import { defineStore } from 'pinia'
import { ref } from 'vue'
import { authApi } from '@/shared/api'
import type { User } from '@/shared/api/types'
import { getToken, setToken } from '@/shared/utils/request'

export const useUserStore = defineStore('user', () => {
  const user = ref<User | null>(null)
  const token = ref(getToken())
  const loaded = ref(false)

  const isLoggedIn = () => !!token.value

  async function login(username: string, password: string) {
    const res = await authApi.login(username, password)
    token.value = res.token
    setToken(res.token)
    user.value = res.user
    loaded.value = true
    return res.user
  }

  async function fetchMe() {
    if (!token.value) {
      loaded.value = true
      return null
    }
    try {
      user.value = await authApi.me()
    } catch {
      user.value = null
    } finally {
      loaded.value = true
    }
    return user.value
  }

  async function logout() {
    try {
      await authApi.logout()
    } catch {
      /* ignore */
    }
    token.value = ''
    setToken('')
    user.value = null
  }

  function clear() {
    token.value = ''
    setToken('')
    user.value = null
  }

  return { user, token, loaded, isLoggedIn, login, fetchMe, logout, clear }
})
