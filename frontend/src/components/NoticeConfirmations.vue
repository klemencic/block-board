<script setup lang="ts">
import type { LeaseTenant } from '@/api/leases'
import { formatDateTime } from '@/utils/format'

// Seznam sostanovalcev s stanjem potrditve odpovedi (US-02, kriterij 2).
defineProps<{ tenants: LeaseTenant[] }>()
</script>

<template>
  <ul class="space-y-2">
    <li v-for="t in tenants" :key="t.id" class="flex items-center justify-between gap-3 text-[15px]">
      <span class="flex items-center gap-2">
        <span
          class="grid size-5 place-items-center rounded-full text-[11px] text-white"
          :class="t.notice_confirmed_at ? 'bg-ink' : 'border border-dashed border-muted bg-transparent'"
          >{{ t.notice_confirmed_at ? '✓' : '' }}</span
        >
        {{ t.name }}
      </span>
      <span class="font-mono text-sm text-muted">
        {{ t.notice_confirmed_at ? formatDateTime(t.notice_confirmed_at) : 'še ni potrdil' }}
      </span>
    </li>
  </ul>
</template>
