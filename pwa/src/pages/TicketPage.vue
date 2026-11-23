<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { api } from '@/api'
import { ApiError } from '@/api/http'
import { realtime } from '@/realtime'
import { useNotificationsStore } from '@/stores/notifications'
import { localName } from '@/i18n'
import { assetLabel, dateTime, iconFor, locationLabel, timeAgo } from '@/utils/format'
import StatusBadge from '@/components/StatusBadge.vue'
import PhotoPicker from '@/components/PhotoPicker.vue'
import StarRating from '@/components/StarRating.vue'

const props = defineProps({ number: { type: String, required: true } })
const { t } = useI18n()
const route = useRoute()
const notifications = useNotificationsStore()

const ticket = ref(null)
const loadError = ref('')
const justCreated = ref(route.query.created === '1')

const reply = ref('')
const replyPhotos = ref([])
const reopening = ref(false)
const reopenReason = ref('')
const rating = ref(0)
const ratingComment = ref('')
const busy = ref(false)
const actionError = ref('')

async function load() {
  try {
    ticket.value = (await api.ticket(props.number)).data
    loadError.value = ''
  } catch (e) {
    loadError.value = e instanceof ApiError && [403, 404].includes(e.status) ? t('ticket.notFound') : t('common.loadFailed')
  }
}

// Live: the technician's changes arrive over Reverb. Fallbacks: a
// notification about this ticket, or coming back to the app.
const onVisible = () => document.visibilityState === 'visible' && load()
const channel = `ticket.${props.number}`

onMounted(() => {
  load()
  document.addEventListener('visibilitychange', onVisible)
  realtime()?.private(channel).listen('.ticket.updated', load)
})
onBeforeUnmount(() => {
  document.removeEventListener('visibilitychange', onVisible)
  realtime()?.leave(channel)
})

watch(
  () => notifications.lastEvent,
  (event) => event?.ticket === props.number && load(),
)

const ticketPhotos = computed(() => ticket.value?.photos.filter((p) => p.comment_id === null) ?? [])

/** Status changes and replies, oldest first, as one conversation. */
const timeline = computed(() => {
  if (!ticket.value) return []
  const photosByComment = {}
  for (const photo of ticket.value.photos) {
    if (photo.comment_id) (photosByComment[photo.comment_id] ??= []).push(photo)
  }
  return [
    ...ticket.value.status_history.map((h, i) => ({ key: `s${i}`, type: 'status', at: h.at, ...h })),
    ...ticket.value.comments.map((c) => ({ key: `c${c.id}`, type: 'comment', ...c, photos: photosByComment[c.id] ?? [] })),
  ].sort((a, b) => new Date(a.at) - new Date(b.at))
})

async function run(work) {
  busy.value = true
  actionError.value = ''
  try {
    ticket.value = (await work()).data
    return true
  } catch (e) {
    actionError.value = e instanceof ApiError && e.status === 422 ? Object.values(e.errors).flat()[0] ?? e.message : t('common.networkError')
    return false
  } finally {
    busy.value = false
  }
}

async function sendReply() {
  if (!reply.value.trim()) return
  if (await run(() => api.comment(props.number, reply.value.trim(), replyPhotos.value))) {
    reply.value = ''
    replyPhotos.value = []
  }
}

async function cancelTicket() {
  if (!window.confirm(t('ticket.cancelConfirm'))) return
  await run(() => api.cancel(props.number))
}

const confirmFixed = () => run(() => api.confirm(props.number))

async function reopen() {
  if (await run(() => api.reopen(props.number, reopenReason.value.trim()))) {
    reopening.value = false
    reopenReason.value = ''
  }
}

const sendRating = () => run(() => api.rate(props.number, rating.value, ratingComment.value.trim()))
</script>

<template>
  <section class="space-y-4">
    <RouterLink :to="{ name: 'tickets' }" class="text-sm font-semibold text-brand-700">‹ {{ t('tickets.title') }}</RouterLink>

    <div v-if="loadError" class="card p-6 text-center">{{ loadError }}</div>
    <div v-else-if="!ticket" class="space-y-3">
      <div v-for="i in 3" :key="i" class="card h-24 animate-pulse bg-slate-100" />
    </div>

    <template v-else>
      <div v-if="justCreated" class="rounded-2xl bg-emerald-50 p-4 text-emerald-900" role="status">
        <div class="font-bold">✅ {{ t('ticket.createdTitle') }}</div>
        <div class="text-sm">{{ t('ticket.createdBody') }}</div>
        <button class="mt-1 text-xs underline" @click="justCreated = false">{{ t('common.close') }}</button>
      </div>

      <!-- Header -->
      <div class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div class="flex gap-3">
            <span class="text-3xl" aria-hidden="true">{{ iconFor(ticket.category.parent?.icon) }}</span>
            <div>
              <h1 class="text-lg font-bold leading-snug">
                {{ ticket.category.parent ? `${localName(ticket.category.parent)} › ` : '' }}{{ localName(ticket.category) }}
              </h1>
              <div class="font-mono text-xs text-slate-500">{{ ticket.number }}</div>
            </div>
          </div>
          <StatusBadge :status="ticket.status" :label="ticket.status_label" />
        </div>

        <dl class="mt-4 space-y-2 text-sm">
          <div class="flex gap-2"><dt aria-hidden="true">📍</dt><dd>{{ locationLabel(ticket.location) }}</dd></div>
          <div v-if="ticket.asset" class="flex gap-2">
            <dt aria-hidden="true">{{ iconFor(ticket.asset.type?.icon) }}</dt>
            <dd>{{ localName(ticket.asset.type) }} — {{ assetLabel(ticket.asset) }} <span class="font-mono text-xs text-slate-500">({{ ticket.asset.asset_tag }})</span></dd>
          </div>
          <div class="flex gap-2"><dt aria-hidden="true">🕒</dt><dd :title="dateTime(ticket.created_at)">{{ t('ticket.reported') }} {{ timeAgo(ticket.created_at) }}</dd></div>
          <div class="flex gap-2">
            <dt aria-hidden="true">🧑‍🔧</dt>
            <dd>{{ ticket.assignee ? t('ticket.handledBy', { name: ticket.assignee.name }) : t('ticket.waitingForTechnician') }}</dd>
          </div>
          <div v-if="ticket.is_class_in_progress" class="flex gap-2 font-semibold text-red-700"><dt aria-hidden="true">🎓</dt><dd>{{ t('ticket.urgentClass') }}</dd></div>
        </dl>

        <p v-if="ticket.description" class="mt-4 whitespace-pre-line rounded-xl bg-slate-50 p-3 text-sm">{{ ticket.description }}</p>

        <div v-if="ticketPhotos.length" class="mt-3 flex flex-wrap gap-2">
          <a v-for="photo in ticketPhotos" :key="photo.id" :href="photo.url" target="_blank" rel="noopener">
            <img :src="photo.url" alt="" loading="lazy" class="size-20 rounded-xl object-cover" />
          </a>
        </div>
      </div>

      <p v-if="actionError" class="rounded-xl bg-red-50 p-3 text-sm text-red-800" role="alert">{{ actionError }}</p>

      <!-- Resolved: was it really fixed? -->
      <div v-if="ticket.can.confirm || ticket.can.reopen" class="card space-y-3 border-emerald-300 bg-emerald-50 p-5">
        <div class="font-bold">{{ t('ticket.wasItFixed') }}</div>
        <div v-if="!reopening" class="grid grid-cols-2 gap-2">
          <button v-if="ticket.can.confirm" class="btn-primary !bg-emerald-700" :disabled="busy" @click="confirmFixed">👍 {{ t('ticket.yesFixed') }}</button>
          <button v-if="ticket.can.reopen" class="btn-secondary" :disabled="busy" @click="reopening = true">👎 {{ t('ticket.notFixed') }}</button>
        </div>
        <form v-else class="space-y-2" @submit.prevent="reopen">
          <textarea v-model="reopenReason" class="field" rows="2" required :placeholder="t('ticket.reopenReason')" />
          <div class="grid grid-cols-2 gap-2">
            <button type="submit" class="btn-danger" :disabled="busy || !reopenReason.trim()">{{ t('ticket.reopen') }}</button>
            <button type="button" class="btn-secondary" @click="reopening = false">{{ t('common.cancel') }}</button>
          </div>
        </form>
      </div>

      <!-- Rate after closing -->
      <form v-if="ticket.can.rate" class="card space-y-3 p-5 text-center" @submit.prevent="sendRating">
        <div class="font-bold">{{ t('ticket.rateTitle') }}</div>
        <StarRating v-model="rating" />
        <textarea v-if="rating" v-model="ratingComment" class="field" rows="2" :placeholder="t('ticket.rateComment')" />
        <button type="submit" class="btn-primary w-full" :disabled="busy || !rating">{{ t('ticket.sendRating') }}</button>
      </form>
      <div v-else-if="ticket.rating" class="card p-4 text-center">
        <div class="text-2xl text-amber-400" dir="ltr">{{ '★'.repeat(ticket.rating) }}<span class="text-slate-300">{{ '★'.repeat(5 - ticket.rating) }}</span></div>
        <div class="text-sm text-slate-500">{{ t('ticket.thanksForRating') }}</div>
      </div>

      <!-- Conversation -->
      <div class="card p-5">
        <h2 class="mb-4 font-bold">{{ t('ticket.history') }}</h2>
        <ol class="space-y-4">
          <li v-for="item in timeline" :key="item.key">
            <div v-if="item.type === 'status'" class="flex gap-3 text-sm">
              <span class="mt-1.5 size-2.5 shrink-0 rounded-full bg-brand-600" />
              <div>
                <span class="font-semibold">{{ item.to_label }}</span>
                <span class="text-slate-500"> · {{ timeAgo(item.at) }}</span>
                <p v-if="item.note" class="mt-0.5 text-slate-700">{{ item.note }}</p>
              </div>
            </div>
            <div v-else class="flex" :class="item.is_mine ? 'justify-start' : 'justify-end'">
              <div class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm" :class="item.is_mine ? 'bg-brand-700 text-white' : 'bg-slate-100'">
                <div v-if="!item.is_mine" class="mb-0.5 text-xs font-semibold text-slate-600">{{ item.by.name }}</div>
                <div class="whitespace-pre-line">{{ item.body }}</div>
                <div v-if="item.photos.length" class="mt-2 flex flex-wrap gap-1.5">
                  <a v-for="photo in item.photos" :key="photo.id" :href="photo.url" target="_blank" rel="noopener">
                    <img :src="photo.url" alt="" loading="lazy" class="size-16 rounded-lg object-cover" />
                  </a>
                </div>
                <div class="mt-1 text-[11px] opacity-70">{{ timeAgo(item.at) }}</div>
              </div>
            </div>
          </li>
        </ol>

        <form v-if="ticket.can.comment" class="mt-5 space-y-3 border-t border-slate-100 pt-4" @submit.prevent="sendReply">
          <textarea v-model="reply" class="field" rows="2" maxlength="2000" :placeholder="t('ticket.replyPlaceholder')" />
          <PhotoPicker v-model="replyPhotos" :max="Math.max(0, 5 - ticket.photos.length)" />
          <button type="submit" class="btn-primary w-full" :disabled="busy || !reply.trim()">{{ t('ticket.sendReply') }}</button>
        </form>
      </div>

      <button v-if="ticket.can.cancel" class="btn-danger w-full" :disabled="busy" @click="cancelTicket">{{ t('ticket.cancel') }}</button>
    </template>
  </section>
</template>
