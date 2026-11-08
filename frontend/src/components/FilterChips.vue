<script setup lang="ts" generic="T extends string | number | null">
// Filtri v obliki tabletk, kot v wireframu »Pregled stavbe« (Vsa · 12, Zasedeno · 9 …).
defineProps<{ options: { value: T; label: string; count?: number }[] }>()
const model = defineModel<T>({ required: true })
</script>

<template>
  <div class="flex flex-wrap gap-2">
    <button
      v-for="option in options"
      :key="String(option.value)"
      type="button"
      class="rounded-full border px-4 py-1.5 text-[15px] transition"
      :class="model === option.value ? 'border-ink bg-ink text-white' : 'border-line bg-surface hover:bg-subtle'"
      @click="model = option.value"
    >
      {{ option.label }}<template v-if="option.count !== undefined"> · {{ option.count }}</template>
    </button>
  </div>
</template>
