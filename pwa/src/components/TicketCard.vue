<script setup>
import { useI18n } from 'vue-i18n'
import StatusBadge from './StatusBadge.vue'
import { iconFor, locationLabel, timeAgo } from '@/utils/format'
import { localName } from '@/i18n'

defineProps({ ticket: { type: Object, required: true } })
const { t } = useI18n()
</script>

<template>
  <RouterLink :to="{ name: 'ticket', params: { number: ticket.number } }" class="card flex gap-3 p-4 transition hover:border-brand-600">
    <span class="text-2xl" aria-hidden="true">{{ iconFor(ticket.category?.parent?.icon) }}</span>
    <div class="min-w-0 flex-1">
      <div class="flex items-start justify-between gap-2">
        <div class="font-semibold">
          {{ ticket.category?.parent ? `${localName(ticket.category.parent)} › ` : '' }}{{ localName(ticket.category) }}
        </div>
        <StatusBadge :status="ticket.status" :label="ticket.status_label" />
      </div>
      <div class="mt-1 truncate text-sm text-slate-600">{{ locationLabel(ticket.location) }}</div>
      <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
        <span class="font-mono">{{ ticket.number }}</span>
        <span>{{ timeAgo(ticket.created_at) }}</span>
        <span v-if="ticket.asset">🏷️ {{ ticket.asset.asset_tag }}</span>
        <span v-if="ticket.is_class_in_progress" class="font-semibold text-red-700">🎓 {{ t('ticket.urgentClass') }}</span>
      </div>
    </div>
  </RouterLink>
</template>
