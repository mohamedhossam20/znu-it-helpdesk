<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useNotificationsStore } from '@/stores/notifications'
import { timeAgo } from '@/utils/format'

const { t } = useI18n()
const router = useRouter()
const notifications = useNotificationsStore()

onMounted(() => notifications.refreshCount())

async function open(item) {
  if (!item.read_at) notifications.markRead([item.id]).catch(() => {})
  if (item.ticket_number) router.push({ name: 'ticket', params: { number: item.ticket_number } })
}
</script>

<template>
  <section class="space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">{{ t('notifications.title') }}</h1>
      <button
        v-if="notifications.unread"
        class="text-sm font-semibold text-brand-700"
        @click="notifications.markRead('all')"
      >
        {{ t('notifications.markAllRead') }}
      </button>
    </div>

    <p v-if="notifications.items.length === 0" class="card p-6 text-center text-slate-500">
      🔕 {{ t('notifications.empty') }}
    </p>

    <ul v-else class="card divide-y divide-slate-100 overflow-hidden">
      <li v-for="item in notifications.items" :key="item.id">
        <button
          type="button"
          class="flex w-full gap-3 p-4 text-start transition hover:bg-slate-50"
          :class="{ 'bg-brand-50': !item.read_at }"
          @click="open(item)"
        >
          <span class="mt-1.5 size-2.5 shrink-0 rounded-full" :class="item.read_at ? 'bg-transparent' : 'bg-brand-600'" />
          <span class="min-w-0 flex-1">
            <span class="block font-semibold">{{ item.title }}</span>
            <span class="block text-sm text-slate-600">{{ item.body }}</span>
            <span class="mt-1 block text-xs text-slate-400">{{ timeAgo(item.created_at) }}</span>
          </span>
        </button>
      </li>
    </ul>

    <button
      v-if="notifications.page < notifications.lastPage"
      class="btn-secondary w-full"
      :disabled="notifications.loading"
      @click="notifications.loadMore()"
    >
      {{ t('tickets.loadMore') }}
    </button>
  </section>
</template>
