<script setup lang="ts">
import StatusChip from './StatusChip.vue'
import type { LeaseStatus } from '@/api/leases'

defineProps<{ status: LeaseStatus; label: string }>()

const tones = {
  draft: 'dashed',
  active: 'neutral',
  in_notice: 'warning',
  ended: 'neutral',
} as const
</script>

<template>
  <StatusChip :tone="tones[status]" class="first-letter:uppercase">{{ label }}</StatusChip>
</template>
