<script setup lang="ts">
import StatusChip from './StatusChip.vue'
import type { ChargeStatus } from '@/api/charges'
import { daysLabel } from '@/utils/format'

defineProps<{ status: ChargeStatus; daysOverdue?: number }>()
</script>

<template>
  <StatusChip v-if="status === 'paid'" tone="success">Plačano</StatusChip>
  <StatusChip v-else-if="status === 'overdue'" tone="danger">
    Zamuja<template v-if="daysOverdue"> · {{ daysLabel(daysOverdue) }}</template>
  </StatusChip>
  <StatusChip v-else tone="neutral">Odprto</StatusChip>
</template>
