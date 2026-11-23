<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/api'
import TicketCard from '@/components/TicketCard.vue'

const { t } = useI18n()

const tab = ref('open')
const tickets = ref([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)
const error = ref(false)

async function load(reset = true) {
  error.value = false
  loading.value = true
  if (reset) {
    page.value = 1
    tickets.value = []
  }
  try {
    const response = await api.tickets(tab.value, page.value)
    tickets.value = [...tickets.value, ...response.data]
    lastPage.value = response.meta?.last_page ?? 1
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

function more() {
  page.value++
  load(false)
}

watch(tab, () => load(), { immediate: true })
</script>

<template>
  <section class="space-y-4">
    <h1 class="text-2xl font-bold">{{ t('tickets.title') }}</h1>

    <div class="grid grid-cols-2 rounded-xl bg-slate-200 p-1 text-sm font-semibold" role="tablist">
      <button
        v-for="name in ['open', 'closed']"
        :key="name"
        role="tab"
        :aria-selected="tab === name"
        class="rounded-lg py-2 transition"
        :class="tab === name ? 'bg-white shadow' : 'text-slate-600'"
        @click="tab = name"
      >
        {{ t(`tickets.${name}`) }}
      </button>
    </div>

    <div v-if="error" class="card p-4 text-center text-sm">
      {{ t('common.loadFailed') }}
      <button class="ms-2 font-semibold text-brand-700" @click="load()">{{ t('common.retry') }}</button>
    </div>

    <div class="space-y-3">
      <TicketCard v-for="ticket in tickets" :key="ticket.number" :ticket="ticket" />
    </div>

    <div v-if="loading" class="card h-24 animate-pulse bg-slate-100" />

    <p v-if="!loading && !error && tickets.length === 0" class="card p-6 text-center text-slate-500">
      {{ tab === 'open' ? t('tickets.emptyOpen') : t('tickets.emptyClosed') }}
    </p>

    <button v-if="!loading && page < lastPage" class="btn-secondary w-full" @click="more">{{ t('tickets.loadMore') }}</button>
  </section>
</template>
