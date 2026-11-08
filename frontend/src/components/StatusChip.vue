<script setup lang="ts">
import { computed } from 'vue'

// Oznake stanj v slogu wireframov: polna (zasedeno), poudarjena (odpovedni rok), črtkana (prosto).
const props = defineProps<{ tone?: 'neutral' | 'warning' | 'dashed' | 'primary' | 'danger' | 'success' }>()

const classes = computed(
  () =>
    ({
      neutral: 'bg-subtle text-ink',
      warning: 'bg-warning-soft text-warning',
      dashed: 'border border-dashed border-muted text-ink',
      primary: 'bg-primary-soft text-primary font-semibold',
      danger: 'bg-danger-soft text-danger',
      success: 'bg-[#e7f4ec] text-success',
    })[props.tone ?? 'neutral'],
)
</script>

<template>
  <span class="inline-flex items-center rounded-full px-3 py-0.5 text-sm whitespace-nowrap" :class="classes">
    <slot />
  </span>
</template>
