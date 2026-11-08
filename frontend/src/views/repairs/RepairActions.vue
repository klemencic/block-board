<script setup lang="ts">
import type { Repair } from '@/api/repairs'

// Plošča »Vaš korak« (WF-03). Koraki posameznih vlog se dodajo v US-10 do US-12.
defineProps<{ repair: Repair }>()
defineEmits<{ updated: [repair: Repair] }>()
</script>

<template>
  <aside class="h-fit">
    <div class="card p-7 text-[15px] text-muted">
      <p class="eyebrow">Stanje</p>
      <p class="mt-2">Prijava je v stanju »{{ repair.status_label }}«. O vsaki spremembi boste obveščeni po e-pošti.</p>
    </div>
  </aside>
</template>
