import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { getToken, removeToken } from '@/api/client'
import { getUserInfo } from '@/api/user'
import type { UserInfo } from '@/api/types'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(getToken())
  const user = ref<UserInfo | null>(null)
  const loading = ref(false)
  // is_admin comes from login response auth_data, not from /user/info
  const isAdmin = ref(false)

  const isAuthenticated = computed(() => !!token.value && !!user.value)

  async function fetchUser() {
    if (!token.value) return
    loading.value = true
    try {
      const res = (await getUserInfo()) as any
      // res = { status, message, data: UserInfo }
      user.value = res.data ?? null
    } catch {
      removeToken()
      token.value = null
      user.value = null
    } finally {
      loading.value = false
    }
  }

  function setAdmin(value: boolean) {
    isAdmin.value = value
  }

  function logout() {
    removeToken()
    token.value = null
    user.value = null
    isAdmin.value = false
    window.location.hash = '#/login'
  }

  return { token, user, loading, isAuthenticated, isAdmin, setAdmin, fetchUser, logout }
})
