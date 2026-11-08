<script setup lang="ts">
defineProps<{ title: string; eyebrow?: string; crumbs?: { label: string; to?: string }[] }>()
</script>

<template>
  <header class="mb-8 flex flex-wrap items-end justify-between gap-6">
    <div>
      <nav v-if="crumbs" class="mb-2 font-mono text-sm text-muted">
        <template v-for="(crumb, i) in crumbs" :key="i">
          <RouterLink v-if="crumb.to" :to="crumb.to" class="font-sans text-primary underline">{{ crumb.label }}</RouterLink>
          <span v-else>{{ crumb.label }}</span>
          <span v-if="i < crumbs.length - 1" class="mx-2">/</span>
        </template>
      </nav>
      <p v-else-if="eyebrow" class="mb-1 font-mono text-sm text-muted">{{ eyebrow }}</p>
      <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-[44px] leading-tight font-semibold tracking-tight">{{ title }}</h1>
        <slot name="badge" />
      </div>
      <slot name="meta" />
    </div>
    <div class="flex gap-3"><slot name="actions" /></div>
  </header>
</template>
