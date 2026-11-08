<script setup lang="ts">
import { onMounted, ref } from 'vue'
import BaseModal from './BaseModal.vue'
import { chargesApi, type Charge, type ChargeSource } from '@/api/charges'
import { errorMessage } from '@/api/http'
import { formatDate, formatDateTime, formatMoney } from '@/utils/format'

// Izvor obveznosti: položnica z izračunom deleža, rezervacija ali prijava okvare (US-04, #105).
const props = defineProps<{ charge: Charge }>()
const emit = defineEmits<{ close: [] }>()

const source = ref<ChargeSource | null>(null)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    source.value = await chargesApi.source(props.charge.id)
  } catch (e) {
    error.value = errorMessage(e)
  }
})
</script>

<template>
  <BaseModal :eyebrow="charge.type_label" :title="charge.description" @close="emit('close')">
    <p v-if="error" class="rounded-lg bg-danger-soft p-4 text-danger">{{ error }}</p>
    <p v-else-if="!source" class="text-muted">Nalagam …</p>

    <dl v-else-if="source.type === 'utility'" class="space-y-3 text-[15px]">
      <div class="flex justify-between"><dt class="text-muted">Položnica</dt><dd>{{ source.data.bill.type_label }}</dd></div>
      <div class="flex justify-between">
        <dt class="text-muted">Obdobje</dt>
        <dd class="font-mono">{{ formatDate(source.data.bill.period_start) }} – {{ formatDate(source.data.bill.period_end) }}</dd>
      </div>
      <div class="flex justify-between">
        <dt class="text-muted">Skupni znesek položnice</dt>
        <dd class="font-mono">{{ formatMoney(source.data.bill.amount_cents) }}</dd>
      </div>
      <div v-if="source.data.bill.scope === 'building'" class="flex justify-between">
        <dt class="text-muted">Način delitve</dt><dd>{{ source.data.bill.split_method_label }}</dd>
      </div>
      <div class="flex justify-between">
        <dt class="text-muted">Delež stanovanja {{ source.data.apartment.label }}</dt>
        <dd class="font-mono">{{ formatMoney(source.data.apartment.share_cents) }}</dd>
      </div>
      <div class="flex justify-between">
        <dt class="text-muted">Vaši dnevi bivanja</dt>
        <dd class="font-mono">{{ source.data.tenant.days }} / {{ source.data.apartment.days }}</dd>
      </div>
      <div class="flex justify-between border-t border-line pt-3 font-semibold">
        <dt>Vaš delež</dt><dd class="font-mono">{{ formatMoney(source.data.tenant.amount_cents) }}</dd>
      </div>
    </dl>

    <dl v-else-if="source.type === 'booking'" class="space-y-3 text-[15px]">
      <div class="flex justify-between"><dt class="text-muted">Prostor</dt><dd>{{ source.data.space_name }}</dd></div>
      <div class="flex justify-between">
        <dt class="text-muted">Termin</dt>
        <dd class="font-mono">{{ formatDateTime(source.data.starts_at) }} – {{ source.data.ends_at.slice(11, 16) }}</dd>
      </div>
      <div class="flex justify-between">
        <dt class="text-muted">Cena</dt>
        <dd class="font-mono">{{ source.data.hours }} h × {{ formatMoney(source.data.price_per_hour_cents) }}</dd>
      </div>
      <div class="flex justify-between border-t border-line pt-3 font-semibold">
        <dt>Znesek</dt><dd class="font-mono">{{ formatMoney(source.data.fee_cents) }}</dd>
      </div>
    </dl>

    <dl v-else-if="source.type === 'repair'" class="space-y-3 text-[15px]">
      <div class="flex justify-between"><dt class="text-muted">Prijava</dt><dd class="font-mono">{{ source.data.number }}</dd></div>
      <div class="flex justify-between"><dt class="text-muted">Okvara</dt><dd>{{ source.data.title }}</dd></div>
      <div class="flex justify-between"><dt class="text-muted">Stanje</dt><dd>{{ source.data.status_label }}</dd></div>
      <div class="flex justify-between border-t border-line pt-3 font-semibold">
        <dt>Strošek popravila</dt><dd class="font-mono">{{ formatMoney(source.data.cost_cents) }}</dd>
      </div>
      <RouterLink :to="`/okvare/${source.data.id}`" class="btn btn-secondary mt-2 w-full">Odpri prijavo</RouterLink>
    </dl>
  </BaseModal>
</template>
