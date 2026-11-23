<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'

const { t } = useI18n()
const auth = useAuthStore()
const notifications = useNotificationsStore()

const online = ref(navigator.onLine)
const update = () => {
  online.value = navigator.onLine
  if (online.value) notifications.refreshCount()
}

onMounted(() => {
  window.addEventListener('online', update)
  window.addEventListener('offline', update)
})
onBeforeUnmount(() => {
  window.removeEventListener('online', update)
  window.removeEventListener('offline', update)
})

// Start listening once signed in; stop (and drop the socket) on sign-out.
watch(
  () => auth.user,
  (user) => (user ? notifications.start(user) : notifications.stop()),
  { immediate: true },
)

const nav = [
  { to: { name: 'home' }, label: 'nav.home', icon: '🏠' },
  { to: { name: 'new-ticket' }, label: 'nav.newTicket', icon: '➕', primary: true },
  { to: { name: 'tickets' }, label: 'nav.myTickets', icon: '📋' },
  { to: { name: 'profile' }, label: 'nav.profile', icon: '👤' },
]
</script>

<template>
  <div class="min-h-dvh pb-24">
    <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/90 backdrop-blur">
      <div class="mx-auto flex max-w-xl items-center gap-3 px-4 py-3">
        <img src="/icons/icon.svg" alt="" class="size-8" />
        <div class="flex-1 leading-tight">
          <div class="font-bold">{{ t('app.title') }}</div>
          <div class="text-xs text-slate-500">{{ t('app.subtitle') }}</div>
        </div>
        <RouterLink
          :to="{ name: 'notifications' }"
          class="relative flex size-10 items-center justify-center rounded-full hover:bg-slate-100"
          :aria-label="t('notifications.title')"
        >
          <span class="text-xl" aria-hidden="true">🔔</span>
          <span
            v-if="notifications.unread"
            class="absolute -top-0.5 -end-0.5 flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[11px] font-bold text-white"
          >{{ notifications.unread > 99 ? '99+' : notifications.unread }}</span>
        </RouterLink>
      </div>
      <div v-if="!online" class="bg-amber-100 px-4 py-2 text-center text-sm font-medium text-amber-900">
        {{ t('common.offline') }}
      </div>
    </header>

    <main class="mx-auto max-w-xl px-4 py-5">
      <slot />
    </main>

    <nav class="fixed inset-x-0 bottom-0 z-10 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)]">
      <div class="mx-auto grid max-w-xl grid-cols-4">
        <RouterLink
          v-for="item in nav"
          :key="item.label"
          :to="item.to"
          class="flex flex-col items-center gap-0.5 py-2 text-xs font-medium text-slate-500"
          active-class="!text-brand-700"
          exact-active-class="!text-brand-700"
        >
          <span
            class="flex size-8 items-center justify-center text-lg"
            :class="item.primary ? 'rounded-full bg-brand-700 text-white shadow' : ''"
            aria-hidden="true"
          >{{ item.icon }}</span>
          {{ t(item.label) }}
        </RouterLink>
      </div>
    </nav>
  </div>
</template>
