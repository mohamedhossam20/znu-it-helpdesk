import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/api'

/**
 * Buildings and problem types change rarely: fetched once per session.
 */
export const useLookupsStore = defineStore('lookups', () => {
  const buildings = ref(null)
  const categories = ref(null)
  const roomsByFloor = ref({})

  async function loadBuildings() {
    buildings.value ??= (await api.buildings()).data
    return buildings.value
  }

  async function loadCategories() {
    categories.value ??= (await api.categories()).data
    return categories.value
  }

  async function loadRooms(floorId) {
    roomsByFloor.value[floorId] ??= (await api.rooms(floorId)).data
    return roomsByFloor.value[floorId]
  }

  function reset() {
    buildings.value = null
    categories.value = null
    roomsByFloor.value = {}
  }

  return { buildings, categories, roomsByFloor, loadBuildings, loadCategories, loadRooms, reset }
})
