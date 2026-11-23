<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { iconFor } from '@/utils/format'
import { localName } from '@/i18n'

/**
 * Two taps: main type card, then the exact problem. Main types without
 * sub-types (e.g. "Other") are selected directly.
 */
const props = defineProps({
  categories: { type: Array, required: true },
  modelValue: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const parent = ref(props.categories.find((c) => c.children?.some((ch) => ch.id === props.modelValue?.id)) ?? null)
const children = computed(() => parent.value?.children ?? [])

function pickParent(category) {
  if (!category.children?.length) {
    parent.value = null
    emit('update:modelValue', { ...category, parent: null })
    return
  }
  parent.value = category
  emit('update:modelValue', null)
}

function pickChild(category) {
  emit('update:modelValue', { ...category, parent: parent.value })
}
</script>

<template>
  <div v-if="!parent" class="grid grid-cols-2 gap-3">
    <button
      v-for="category in categories"
      :key="category.id"
      type="button"
      class="choice flex-col !items-center !gap-2 !p-4 text-center"
      :class="{ 'choice-selected': modelValue?.id === category.id }"
      @click="pickParent(category)"
    >
      <span class="text-3xl" aria-hidden="true">{{ iconFor(category.icon) }}</span>
      <span class="font-semibold">{{ localName(category) }}</span>
    </button>
  </div>

  <div v-else class="space-y-3">
    <button type="button" class="text-sm font-semibold text-brand-700" @click="parent = null; emit('update:modelValue', null)">
      ‹ {{ t('new.otherProblemType') }}
    </button>
    <div class="flex items-center gap-2 text-lg font-bold">
      <span aria-hidden="true">{{ iconFor(parent.icon) }}</span>{{ localName(parent) }}
    </div>
    <button
      v-for="category in children"
      :key="category.id"
      type="button"
      class="choice"
      :class="{ 'choice-selected': modelValue?.id === category.id }"
      @click="pickChild(category)"
    >
      <span class="flex-1 font-medium">{{ localName(category) }}</span>
      <span v-if="modelValue?.id === category.id" class="text-brand-700" aria-hidden="true">✔</span>
    </button>
  </div>
</template>
