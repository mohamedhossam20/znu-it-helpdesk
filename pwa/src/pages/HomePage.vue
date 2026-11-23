<script setup>
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { locationLabel } from '@/utils/format'
import TicketCard from '@/components/TicketCard.vue'

const { t } = useI18n()
const auth = useAuthStore()
const notifications = useNotificationsStore()

const tickets = ref(null)
const error = ref(false)

async function load() {
  error.value = false
  try {
    tickets.value = (await api.tickets('open')).data
  } catch {
    error.value = true
  }
}

onMounted(load)

// A technician picked up or resolved something: refresh the list.
watch(() => notifications.lastEvent, load)
</script>

<template>
  <section class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold">{{ t('home.greeting', { name: auth.user?.name }) }}</h1>
      <p v-if="auth.hasFixedLocation" class="mt-1 text-sm text-slate-600">
        📍 {{ locationLabel(auth.user.default_room) }}
      </p>
    </div>

    <RouterLink :to="{ name: 'new-ticket' }" class="btn-primary w-full !py-5 text-lg">
      <span aria-hidden="true">🛠️</span> {{ t('home.newTicket') }}
    </RouterLink>
    <p class="-mt-3 text-center text-sm text-slate-500">
      {{ auth.hasFixedLocation ? t('home.newTicketHintFixed') : t('home.newTicketHint') }}
    </p>

    <div>
      <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-bold">{{ t('home.openTickets') }}</h2>
        <RouterLink :to="{ name: 'tickets' }" class="text-sm font-semibold text-brand-700">{{ t('home.viewAll') }}</RouterLink>
      </div>

      <div v-if="error" class="card p-4 text-center text-sm">
        {{ t('common.loadFailed') }}
        <button class="ms-2 font-semibold text-brand-700" @click="load">{{ t('common.retry') }}</button>
      </div>
      <div v-else-if="tickets === null" class="space-y-3">
        <div v-for="i in 2" :key="i" class="card h-24 animate-pulse bg-slate-100" />
      </div>
      <p v-else-if="tickets.length === 0" class="card p-6 text-center text-slate-500">✅ {{ t('home.noOpen') }}</p>
      <div v-else class="space-y-3">
        <TicketCard v-for="ticket in tickets" :key="ticket.number" :ticket="ticket" />
      </div>
    </div>
  </section>
</template>
