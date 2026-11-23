<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { api } from '@/api'
import { ApiError } from '@/api/http'
import { useLookupsStore } from '@/stores/lookups'
import { localName } from '@/i18n'
import { assetLabel, iconFor, locationLabel } from '@/utils/format'
import LocationPicker from '@/components/LocationPicker.vue'
import CategoryPicker from '@/components/CategoryPicker.vue'
import PhotoPicker from '@/components/PhotoPicker.vue'

/**
 * Three ways in:
 *  - "My device / office": place and devices come from the profile.
 *  - "Another place": building → floor → room, then that room's devices.
 *  - QR sticker (/r/:token): the room is already known.
 */
const props = defineProps({ token: { type: String, default: null } })

const { t } = useI18n()
const router = useRouter()
const lookups = useLookupsStore()

const STEPS = ['where', 'device', 'problem', 'details']
const CLASS_ROOM_TYPES = ['lecture_hall', 'lab', 'meeting_room']

const loading = ref(true)
const loadError = ref('')
const context = ref(null)

const step = ref('where')
const mode = ref(null) // 'my_device' | 'other_location' | 'qr'
const room = ref(null)
const devices = ref([])
const asset = ref(null)
const category = ref(null)
const classNow = ref(false)
const priority = ref('')
const description = ref('')
const photos = ref([])

const submitting = ref(false)
const submitError = ref('')
const fieldErrors = ref({})

const stepIndex = computed(() => STEPS.indexOf(step.value))
const roomLocation = computed(() => room.value?.location ?? null)
const canBeClass = computed(() => CLASS_ROOM_TYPES.includes(room.value?.type))

onMounted(async () => {
  try {
    await lookups.loadCategories()

    if (props.token) {
      const scanned = (await api.roomByQr(props.token)).data
      mode.value = 'qr'
      room.value = scanned
      devices.value = scanned.assets ?? []
      step.value = devices.value.length ? 'device' : 'problem'
      return
    }

    context.value = (await api.context()).data
    if (!context.value.default_room) mode.value = 'other_location'
  } catch (e) {
    loadError.value = e instanceof ApiError && e.status === 404 ? t('new.qrInvalid') : t('common.loadFailed')
  } finally {
    loading.value = false
  }
})

function chooseMyDevice() {
  mode.value = 'my_device'
  room.value = context.value.default_room
  devices.value = [...context.value.my_assets, ...context.value.room_assets]
  asset.value = null
  step.value = devices.value.length ? 'device' : 'problem'
}

function chooseOtherPlace() {
  mode.value = 'other_location'
  room.value = null
}

async function pickRoom(selected) {
  if (!selected) return
  room.value = selected
  asset.value = null
  try {
    devices.value = (await api.roomAssets(selected.id)).data
  } catch {
    devices.value = []
  }
  step.value = devices.value.length ? 'device' : 'problem'
}

function pickDevice(selected) {
  asset.value = selected
  step.value = 'problem'
}

function pickCategory(selected) {
  category.value = selected
  if (selected) step.value = 'details'
}

function back() {
  submitError.value = ''
  if (step.value === 'details') step.value = 'problem'
  else if (step.value === 'problem') step.value = devices.value.length ? 'device' : (mode.value === 'qr' ? 'problem' : 'where')
  else if (step.value === 'device' && mode.value !== 'qr') {
    step.value = 'where'
    if (mode.value === 'other_location') room.value = null
  } else router.back()
}

const showBack = computed(() => !(mode.value === 'qr' && step.value === (devices.value.length ? 'device' : 'problem')) && step.value !== 'where')

async function submit() {
  submitting.value = true
  submitError.value = ''
  fieldErrors.value = {}

  try {
    const response = await api.createTicket(
      {
        source: mode.value,
        room_id: room.value.id,
        asset_id: asset.value?.id,
        category_id: category.value.id,
        priority: classNow.value ? '' : priority.value,
        is_class_in_progress: classNow.value,
        description: description.value.trim(),
      },
      photos.value,
    )
    router.replace({ name: 'ticket', params: { number: response.data.number }, query: { created: '1' } })
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      fieldErrors.value = e.errors
      submitError.value = Object.values(e.errors).flat()[0] ?? e.message
    } else if (e instanceof ApiError && e.status === 429) {
      submitError.value = e.message
    } else {
      submitError.value = t('common.networkError')
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <section class="space-y-5">
    <div class="flex items-center gap-3">
      <button v-if="showBack" type="button" class="btn-secondary !px-3 !py-2" :aria-label="t('common.back')" @click="back">
        <span class="inline-block rtl:rotate-180" aria-hidden="true">←</span>
      </button>
      <h1 class="text-2xl font-bold">{{ t('new.title') }}</h1>
    </div>

    <ol class="grid grid-cols-4 gap-2" :aria-label="t('new.progress')">
      <li
        v-for="(name, index) in STEPS"
        :key="name"
        class="h-1.5 rounded-full"
        :class="index <= stepIndex ? 'bg-brand-700' : 'bg-slate-200'"
      />
    </ol>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 2" :key="i" class="card h-20 animate-pulse bg-slate-100" />
    </div>

    <div v-else-if="loadError" class="card space-y-3 p-6 text-center">
      <p>{{ loadError }}</p>
      <RouterLink :to="{ name: 'new-ticket' }" class="btn-secondary">{{ t('new.chooseManually') }}</RouterLink>
    </div>

    <template v-else>
      <!-- Where the scanned QR says we are -->
      <div v-if="mode === 'qr'" class="rounded-2xl bg-brand-50 p-4 text-brand-900">
        <div class="text-sm">{{ t('new.qrYouAreIn') }}</div>
        <div class="font-bold">{{ locationLabel(roomLocation) }}</div>
      </div>

      <!-- 1. Where -->
      <div v-if="step === 'where'" class="space-y-4">
        <h2 class="text-lg font-semibold">{{ t('new.whereTitle') }}</h2>

        <template v-if="context?.default_room">
          <button type="button" class="choice" :class="{ 'choice-selected': mode === 'my_device' }" @click="chooseMyDevice">
            <span class="text-3xl" aria-hidden="true">💻</span>
            <span>
              <span class="block font-bold">{{ t('new.myDevice') }}</span>
              <span class="block text-sm text-slate-600">{{ locationLabel(context.default_room.location) }}</span>
            </span>
          </button>
          <button type="button" class="choice" :class="{ 'choice-selected': mode === 'other_location' }" @click="chooseOtherPlace">
            <span class="text-3xl" aria-hidden="true">🏫</span>
            <span>
              <span class="block font-bold">{{ t('new.otherPlace') }}</span>
              <span class="block text-sm text-slate-600">{{ t('new.otherPlaceHint') }}</span>
            </span>
          </button>
        </template>

        <div v-if="mode === 'other_location'" class="card space-y-4 p-4">
          <div v-if="context?.recent_rooms?.length">
            <div class="label">{{ t('new.recentPlaces') }}</div>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="recent in context.recent_rooms"
                :key="recent.id"
                type="button"
                class="rounded-full border border-slate-300 bg-white px-3 py-1.5 text-sm hover:border-brand-600"
                @click="pickRoom(recent)"
              >
                {{ recent.code }} — {{ localName(recent) }}
              </button>
            </div>
          </div>
          <LocationPicker @select="pickRoom" />
          <p class="text-xs text-slate-500">💡 {{ t('new.qrTip') }}</p>
        </div>
      </div>

      <!-- 2. Device -->
      <div v-else-if="step === 'device'" class="space-y-3">
        <h2 class="text-lg font-semibold">{{ t('new.deviceTitle') }}</h2>
        <p v-if="mode !== 'qr'" class="text-sm text-slate-600">📍 {{ locationLabel(roomLocation) }}</p>
        <button
          v-for="device in devices"
          :key="device.id"
          type="button"
          class="choice"
          :class="{ 'choice-selected': asset?.id === device.id }"
          @click="pickDevice(device)"
        >
          <span class="text-2xl" aria-hidden="true">{{ iconFor(device.type?.icon) }}</span>
          <span class="flex-1">
            <span class="block font-semibold">
              {{ localName(device.type) }}
              <span v-if="device.is_mine" class="ms-1 rounded bg-brand-100 px-1.5 py-0.5 text-xs text-brand-800">{{ t('new.mine') }}</span>
            </span>
            <span class="block text-sm text-slate-600">{{ assetLabel(device) }}</span>
          </span>
          <span class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs">{{ device.asset_tag }}</span>
        </button>
        <button type="button" class="choice" @click="pickDevice(null)">
          <span class="text-2xl" aria-hidden="true">🏢</span>
          <span class="font-semibold">{{ t('new.generalProblem') }}</span>
        </button>
      </div>

      <!-- 3. Problem -->
      <div v-else-if="step === 'problem'" class="space-y-3">
        <h2 class="text-lg font-semibold">{{ t('new.problemTitle') }}</h2>
        <CategoryPicker :categories="lookups.categories" :model-value="category" @update:model-value="pickCategory" />
      </div>

      <!-- 4. Details -->
      <form v-else class="space-y-5" @submit.prevent="submit">
        <div class="card divide-y divide-slate-100">
          <div class="flex items-start gap-3 p-4">
            <span aria-hidden="true">📍</span>
            <div class="flex-1 text-sm">{{ locationLabel(roomLocation) }}</div>
          </div>
          <div v-if="asset" class="flex items-start gap-3 p-4">
            <span aria-hidden="true">{{ iconFor(asset.type?.icon) }}</span>
            <div class="flex-1 text-sm">{{ localName(asset.type) }} — {{ assetLabel(asset) }} <span class="font-mono text-xs text-slate-500">({{ asset.asset_tag }})</span></div>
          </div>
          <div class="flex items-start gap-3 p-4">
            <span aria-hidden="true">⚠️</span>
            <div class="flex-1 text-sm font-semibold">
              {{ category.parent ? `${localName(category.parent)} › ` : '' }}{{ localName(category) }}
            </div>
            <button type="button" class="text-sm font-semibold text-brand-700" @click="step = 'problem'">{{ t('common.change') }}</button>
          </div>
        </div>

        <label v-if="canBeClass" class="card flex items-center gap-3 p-4" :class="{ 'border-red-300 bg-red-50': classNow }">
          <input v-model="classNow" type="checkbox" class="size-5 accent-red-600" />
          <span>
            <span class="block font-semibold">🎓 {{ t('new.classNow') }}</span>
            <span class="block text-xs text-slate-600">{{ t('new.classNowHint') }}</span>
          </span>
        </label>

        <div v-if="!classNow">
          <span class="label">{{ t('new.priority') }}</span>
          <div class="grid grid-cols-4 gap-2 text-sm font-semibold">
            <button
              v-for="option in ['', 'low', 'normal', 'high']"
              :key="option"
              type="button"
              class="rounded-xl border-2 px-2 py-2"
              :class="priority === option ? 'border-brand-700 bg-brand-50 text-brand-800' : 'border-slate-200 bg-white'"
              @click="priority = option"
            >
              {{ t(`new.priorities.${option || 'auto'}`) }}
            </button>
          </div>
        </div>

        <label class="block">
          <span class="label">{{ t('new.description') }} <span class="font-normal text-slate-400">({{ t('common.optional') }})</span></span>
          <textarea v-model="description" class="field" rows="3" maxlength="2000" :placeholder="t('new.descriptionPlaceholder')" />
          <span v-if="fieldErrors.description" class="mt-1 block text-sm text-red-700">{{ fieldErrors.description[0] }}</span>
        </label>

        <div>
          <span class="label">{{ t('photos.title') }} <span class="font-normal text-slate-400">({{ t('common.optional') }})</span></span>
          <PhotoPicker v-model="photos" />
        </div>

        <p v-if="submitError" class="rounded-xl bg-red-50 p-3 text-sm text-red-800" role="alert">{{ submitError }}</p>

        <button type="submit" class="btn-primary w-full !py-4 text-lg" :disabled="submitting">
          {{ submitting ? t('new.sending') : t('new.send') }}
        </button>
      </form>
    </template>
  </section>
</template>
