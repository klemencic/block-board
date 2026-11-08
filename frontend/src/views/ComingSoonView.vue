<script setup lang="ts">
import { useRoute } from 'vue-router'
import PageHeader from '@/components/PageHeader.vue'

const route = useRoute()
</script>

<template>
  <PageHeader :title="String(route.meta.title ?? '')" />
  <div class="card p-10 text-muted">Ta del aplikacije je v pripravi.</div>
</template>
