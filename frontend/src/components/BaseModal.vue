<script setup lang="ts">
defineProps<{ title: string; eyebrow?: string }>()
const emit = defineEmits<{ close: [] }>()
</script>

<template>
  <div class="fixed inset-0 z-50 grid place-items-center bg-ink/40 p-4" @click.self="emit('close')">
    <div class="card-strong max-h-[90vh] w-full max-w-lg overflow-y-auto p-7 shadow-xl" role="dialog" aria-modal="true">
      <div class="flex items-start justify-between gap-4">
        <div>
          <p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p>
          <h2 class="mt-1 text-2xl font-semibold">{{ title }}</h2>
        </div>
        <button class="text-2xl leading-none text-muted hover:text-ink" aria-label="Zapri" @click="emit('close')">×</button>
      </div>
      <div class="mt-5"><slot /></div>
    </div>
  </div>
</template>
