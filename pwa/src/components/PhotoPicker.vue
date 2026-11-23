<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  max: { type: Number, default: 5 },
  maxBytes: { type: Number, default: 5 * 1024 * 1024 },
})
const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const input = ref(null)
const previews = ref([])
const error = ref('')

watch(
  () => props.modelValue,
  (files) => {
    previews.value.forEach((url) => URL.revokeObjectURL(url))
    previews.value = files.map((file) => URL.createObjectURL(file))
  },
  { immediate: true },
)

onBeforeUnmount(() => previews.value.forEach((url) => URL.revokeObjectURL(url)))

function onPick(event) {
  error.value = ''
  const picked = Array.from(event.target.files ?? [])
  event.target.value = ''

  const tooBig = picked.filter((file) => file.size > props.maxBytes)
  if (tooBig.length) error.value = t('photos.tooBig', { mb: Math.round(props.maxBytes / 1024 / 1024) })

  const accepted = picked.filter((file) => file.size <= props.maxBytes && file.type.startsWith('image/'))
  const room = props.max - props.modelValue.length
  if (accepted.length > room) error.value = t('photos.tooMany', { max: props.max })

  emit('update:modelValue', [...props.modelValue, ...accepted.slice(0, Math.max(0, room))])
}

function remove(index) {
  emit('update:modelValue', props.modelValue.filter((_, i) => i !== index))
}
</script>

<template>
  <div>
    <div class="flex flex-wrap gap-2">
      <div v-for="(url, index) in previews" :key="url" class="relative">
        <img :src="url" alt="" class="size-20 rounded-xl object-cover" />
        <button
          type="button"
          class="absolute -top-2 -end-2 flex size-6 items-center justify-center rounded-full bg-slate-800 text-xs text-white"
          :aria-label="t('photos.remove')"
          @click="remove(index)"
        >✕</button>
      </div>
      <button
        v-if="modelValue.length < max"
        type="button"
        class="flex size-20 flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 text-slate-500 hover:border-brand-600 hover:text-brand-700"
        @click="input.click()"
      >
        <span class="text-2xl" aria-hidden="true">📷</span>
        <span class="text-xs">{{ t('photos.add') }}</span>
      </button>
    </div>
    <input ref="input" type="file" accept="image/*" multiple class="hidden" @change="onPick" />
    <p v-if="error" class="mt-2 text-sm text-red-700">{{ error }}</p>
  </div>
</template>
