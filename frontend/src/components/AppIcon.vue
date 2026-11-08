<script setup lang="ts">
// Preproste ikone v slogu wireframov (obroba 1.6 px).
defineProps<{ name: string; size?: number }>()

const paths: Record<string, string> = {
  grid: 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
  home: 'M4 20V10l8-6 8 6v10M9 20v-6h6v6',
  receipt: 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6',
  bolt: 'M13 3 5 14h6l-1 7 8-11h-6z',
  calendar: 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4',
  wrench: 'M14.5 5.5a4 4 0 0 0 4.9 4.9L11 18.8a2 2 0 1 1-2.8-2.8l8.4-8.4M14.5 5.5l2-2',
  user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0',
  megaphone: 'M4 10v4h3l6 4V6L7 10zM17 9a4 4 0 0 1 0 6',
  logout: 'M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10',
  check: 'M5 12l5 5 9-10',
  clock: 'M12 7v5l3 2M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z',
  chevronLeft: 'M15 5l-7 7 7 7',
  chevronRight: 'M9 5l7 7-7 7',
  plus: 'M12 5v14M5 12h14',
  clipboard: 'M8 4h8v3H8zM6 5H5v16h14V5h-1M9 12h6M9 16h4',
}
</script>

<template>
  <svg
    :width="size ?? 20"
    :height="size ?? 20"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.6"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path :d="paths[name] ?? ''" />
  </svg>
</template>
