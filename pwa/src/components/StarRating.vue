<script setup>
defineProps({ modelValue: { type: Number, default: 0 } })
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="flex justify-center gap-1" dir="ltr" role="radiogroup">
    <button
      v-for="star in 5"
      :key="star"
      type="button"
      role="radio"
      :aria-checked="modelValue === star"
      :aria-label="`${star}/5`"
      class="text-4xl transition active:scale-90"
      :class="star <= modelValue ? 'text-amber-400' : 'text-slate-300'"
      @click="emit('update:modelValue', star)"
    >★</button>
  </div>
</template>
