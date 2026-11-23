import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/api'
import { ApiError } from '@/api/http'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const loaded = ref(false)

  const isLoggedIn = computed(() => user.value !== null)
  const hasFixedLocation = computed(() => Boolean(user.value?.default_room))

  async function load() {
    try {
      user.value = (await api.me()).data
    } catch (error) {
      if (!(error instanceof ApiError) || error.status !== 401) throw error
      user.value = null
    } finally {
      loaded.value = true
    }
    return user.value
  }

  async function login(email, password, remember) {
    user.value = (await api.login(email, password, remember)).data
    loaded.value = true
  }

  async function logout() {
    try {
      await api.logout()
    } finally {
      clear()
    }
  }

  function clear() {
    user.value = null
    loaded.value = true
  }

  return { user, loaded, isLoggedIn, hasFixedLocation, load, login, logout, clear }
})
