<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLookupsStore } from '@/stores/lookups'
import { localName } from '@/i18n'

/**
 * Building → floor → room, emitting the chosen room object (or null).
 */
const emit = defineEmits(['select'])
const { t } = useI18n()
const lookups = useLookupsStore()

const buildingId = ref('')
const floorId = ref('')
const roomId = ref('')
const rooms = ref([])
const loadingRooms = ref(false)
const error = ref('')

const building = computed(() => lookups.buildings?.find((b) => b.id === Number(buildingId.value)))
const floors = computed(() => building.value?.floors ?? [])

onMounted(async () => {
  try {
    await lookups.loadBuildings()
    if (lookups.buildings.length === 1) buildingId.value = String(lookups.buildings[0].id)
  } catch {
    error.value = t('common.loadFailed')
  }
})

watch(buildingId, () => {
  floorId.value = floors.value.length === 1 ? String(floors.value[0].id) : ''
})

watch(floorId, async (id) => {
  roomId.value = ''
  rooms.value = []
  if (!id) return
  loadingRooms.value = true
  try {
    rooms.value = await lookups.loadRooms(id)
  } catch {
    error.value = t('common.loadFailed')
  } finally {
    loadingRooms.value = false
  }
})

watch(roomId, (id) => {
  emit('select', rooms.value.find((r) => r.id === Number(id)) ?? null)
})
</script>

<template>
  <div class="space-y-4">
    <label class="block">
      <span class="label">{{ t('new.building') }}</span>
      <select v-model="buildingId" class="field" :disabled="!lookups.buildings">
        <option value="" disabled>{{ t('common.choose') }}</option>
        <option v-for="b in lookups.buildings ?? []" :key="b.id" :value="String(b.id)">{{ localName(b) }}</option>
      </select>
    </label>

    <label class="block">
      <span class="label">{{ t('new.floor') }}</span>
      <select v-model="floorId" class="field" :disabled="!buildingId">
        <option value="" disabled>{{ t('common.choose') }}</option>
        <option v-for="f in floors" :key="f.id" :value="String(f.id)">{{ localName(f) }}</option>
      </select>
    </label>

    <label class="block">
      <span class="label">{{ t('new.room') }}</span>
      <select v-model="roomId" class="field" :disabled="!floorId || loadingRooms">
        <option value="" disabled>{{ loadingRooms ? t('common.loading') : t('common.choose') }}</option>
        <option v-for="r in rooms" :key="r.id" :value="String(r.id)">{{ r.code }} — {{ localName(r) }}</option>
      </select>
    </label>

    <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
  </div>
</template>
