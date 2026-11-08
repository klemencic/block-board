<script setup lang="ts">
import { ref, watch } from 'vue'
import { parseMoney } from '@/utils/format'

// Vnos zneska v evrih (»90,00«), navzven pa vrednost v centih.
const model = defineModel<number | null>({ required: true })
defineProps<{ id?: string }>()

const toText = (cents: number | null) => (cents === null ? '' : (cents / 100).toFixed(2).replace('.', ','))
const text = ref(toText(model.value))

watch(model, (value) => {
  if (parseMoney(text.value) !== value) text.value = toText(value)
})

function onInput(event: Event) {
  text.value = (event.target as HTMLInputElement).value
  model.value = parseMoney(text.value)
}
</script>

<template>
  <div class="relative">
    <input
      :id="id"
      :value="text"
      inputmode="decimal"
      class="field-input pr-9 font-mono"
      placeholder="0,00"
      @input="onInput"
      @blur="text = toText(model)"
    />
    <span class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-muted">€</span>
  </div>
</template>
