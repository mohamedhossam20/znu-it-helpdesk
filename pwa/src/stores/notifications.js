import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/api'
import { connectRealtime, disconnectRealtime } from '@/realtime'

const POLL_MS = 60_000

export const useNotificationsStore = defineStore('notifications', () => {
  const items = ref([])
  const unread = ref(0)
  const page = ref(1)
  const lastPage = ref(1)
  const loading = ref(false)
  /** Bumped on every live notification so open pages can refresh. */
  const lastEvent = ref(null)

  let timer = null
  let userId = null

  async function refreshCount() {
    try {
      const response = await api.notifications(1)
      unread.value = response.meta.unread_count
      if (page.value === 1) {
        items.value = response.data
        lastPage.value = response.meta.last_page
      }
    } catch {
      // Background refresh: stay quiet, the next tick retries.
    }
  }

  async function loadMore() {
    if (loading.value || page.value >= lastPage.value) return
    loading.value = true
    try {
      const response = await api.notifications(page.value + 1)
      page.value = response.meta.current_page
      items.value = [...items.value, ...response.data]
    } finally {
      loading.value = false
    }
  }

  async function markRead(ids) {
    const response = await api.markNotificationsRead(ids)
    unread.value = response.meta.unread_count
    const now = new Date().toISOString()
    items.value = items.value.map((n) => (ids === 'all' || ids.includes(n.id) ? { ...n, read_at: n.read_at ?? now } : n))
  }

  function start(user) {
    if (userId === user.id) return
    stop()
    userId = user.id
    page.value = 1
    refreshCount()
    timer = setInterval(refreshCount, POLL_MS)

    connectRealtime()
      ?.private(`App.Models.User.${user.id}`)
      .notification((notification) => {
        lastEvent.value = { at: Date.now(), ticket: notification.ticket_number }
        refreshCount()
      })
  }

  function stop() {
    clearInterval(timer)
    timer = null
    userId = null
    items.value = []
    unread.value = 0
    disconnectRealtime()
  }

  return { items, unread, lastPage, page, loading, lastEvent, refreshCount, loadMore, markRead, start, stop }
})
