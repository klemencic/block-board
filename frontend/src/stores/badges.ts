import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Številke ob postavkah menija (npr. odprte prijave okvar, neprebrana obvestila)
 * in oznaka stanovanja najemnika v stranskem meniju.
 */
export const useBadgesStore = defineStore('badges', () => {
  const counts = ref<Record<string, number>>({})
  const apartmentLabel = ref<string | null>(null)

  function set(path: string, value: number): void {
    counts.value = { ...counts.value, [path]: value }
  }

  return { counts, apartmentLabel, set }
})
