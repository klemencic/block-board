<script setup lang="ts">
import { onMounted, ref } from 'vue'
import PageHeader from '@/components/PageHeader.vue'
import ChargeStatusChip from '@/components/ChargeStatusChip.vue'
import ChargeSourceModal from '@/components/ChargeSourceModal.vue'
import { chargesApi, type Charge, type ChargeMonth } from '@/api/charges'
import { useAuthStore } from '@/stores/auth'
import { formatDate, formatMoney, formatPeriod } from '@/utils/format'

const auth = useAuthStore()
const months = ref<ChargeMonth[]>([])
const loading = ref(true)
const selected = ref<Charge | null>(null)

onMounted(async () => {
  months.value = await chargesApi.mine(auth.buildingId)
  loading.value = false
})

function open(charge: Charge) {
  if (charge.has_source) selected.value = charge
}
</script>

<template>
  <PageHeader title="Moje obveznosti" :eyebrow="auth.membership?.building.name" />

  <p v-if="loading" class="text-muted">Nalagam …</p>
  <div v-else-if="months.length === 0" class="card p-10 text-muted">Nimate še nobenih obveznosti.</div>

  <section v-for="month in months" :key="month.period" class="card mb-6 overflow-hidden">
    <header class="flex flex-wrap items-end justify-between gap-4 px-6 pt-6 pb-4">
      <h2 class="text-2xl font-semibold first-letter:uppercase">{{ formatPeriod(month.period) }}</h2>
      <div class="flex gap-8 text-right">
        <div>
          <p class="text-sm text-muted">Skupaj</p>
          <p class="font-mono text-lg">{{ formatMoney(month.total_cents) }}</p>
        </div>
        <div>
          <p class="text-sm text-muted">Še odprto</p>
          <p class="font-mono text-lg font-semibold" :class="month.open_cents > 0 ? 'text-ink' : 'text-muted'">
            {{ formatMoney(month.open_cents) }}
          </p>
        </div>
      </div>
    </header>
    <table class="w-full text-[15px]">
      <thead class="border-y border-line">
        <tr>
          <th class="table-head">Vrsta</th>
          <th class="table-head">Opis</th>
          <th class="table-head">Rok plačila</th>
          <th class="table-head">Stanje</th>
          <th class="table-head text-right">Znesek</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="charge in month.charges"
          :key="charge.id"
          class="border-b border-line last:border-0"
          :class="[
            charge.has_source ? 'cursor-pointer hover:bg-canvas' : '',
            charge.status === 'overdue' ? 'bg-danger-row' : '',
          ]"
          @click="open(charge)"
        >
          <td class="px-6 py-4 first-letter:uppercase">{{ charge.type_label }}</td>
          <td class="px-6 py-4">
            {{ charge.description }}
            <span v-if="charge.has_source" class="ml-1 text-sm text-primary underline">izvor</span>
          </td>
          <td class="px-6 py-4 font-mono">{{ formatDate(charge.due_date) }}</td>
          <td class="px-6 py-4"><ChargeStatusChip :status="charge.status" :days-overdue="charge.days_overdue" /></td>
          <td class="px-6 py-4 text-right font-mono" :class="charge.status === 'overdue' ? 'font-semibold text-danger' : ''">
            {{ formatMoney(charge.amount_cents) }}
          </td>
        </tr>
      </tbody>
    </table>
  </section>

  <ChargeSourceModal v-if="selected" :charge="selected" @close="selected = null" />
</template>
