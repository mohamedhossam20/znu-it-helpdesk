<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, required: true },
  label: { type: String, required: true },
})

const COLORS = {
  new: 'bg-sky-100 text-sky-800',
  acknowledged: 'bg-indigo-100 text-indigo-800',
  in_progress: 'bg-amber-100 text-amber-900',
  on_hold: 'bg-slate-200 text-slate-700',
  resolved: 'bg-emerald-100 text-emerald-800',
  reopened: 'bg-red-100 text-red-800',
  closed: 'bg-slate-100 text-slate-600',
  cancelled: 'bg-slate-100 text-slate-500 line-through',
}

const classes = computed(() => COLORS[props.status] ?? 'bg-slate-100 text-slate-700')
</script>

<template>
  <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-semibold" :class="classes">
    {{ label }}
  </span>
</template>
