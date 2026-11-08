<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseModal from '@/components/BaseModal.vue'
import FilterChips from '@/components/FilterChips.vue'
import MoneyInput from '@/components/MoneyInput.vue'
import ChargeStatusChip from '@/components/ChargeStatusChip.vue'
import ChargeSourceModal from '@/components/ChargeSourceModal.vue'
import { buildingChargesApi, type BuildingTenant, type Charge, type ChargeStatus } from '@/api/charges'
import { leasesApi, type Apartment } from '@/api/leases'
import { errorMessage, validationErrors, type ValidationErrors } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { formatDate, formatMoney, formatPeriod, todayIso } from '@/utils/format'

const auth = useAuthStore()
const buildingId = computed(() => auth.buildingId!)

const charges = ref<Charge[]>([])
const periods = ref<string[]>([])
const apartments = ref<Apartment[]>([])
const loading = ref(true)

const filters = reactive({
  period: '' as string,
  apartment_id: null as number | null,
  status: null as ChargeStatus | null,
})

async function load() {
  loading.value = true
  charges.value = await buildingChargesApi.list(buildingId.value, {
    period: filters.period || undefined,
    apartment_id: filters.apartment_id ?? undefined,
    status: filters.status ?? undefined,
  })
  loading.value = false
}

onMounted(async () => {
  ;[periods.value, apartments.value] = await Promise.all([
    buildingChargesApi.periods(buildingId.value),
    leasesApi.apartments(buildingId.value),
  ])
  await load()
})
watch(filters, load)

const total = computed(() => charges.value.reduce((sum, c) => sum + c.amount_cents, 0))

// Označi kot plačano (US-05, #106)
const paying = ref<Charge | null>(null)
const paidOn = ref(todayIso())
const payError = ref<string | null>(null)

function openPay(charge: Charge) {
  paying.value = charge
  paidOn.value = todayIso()
  payError.value = null
}

async function confirmPaid() {
  try {
    const updated = await buildingChargesApi.markPaid(paying.value!.id, paidOn.value)
    charges.value = charges.value.map((c) => (c.id === updated.id ? updated : c))
    paying.value = null
  } catch (e) {
    payError.value = validationErrors(e).paid_on?.[0] ?? errorMessage(e)
  }
}

// Popravek ali dobropis kot nova obveznost vrste »drugo« (US-05, #107)
const correcting = ref(false)
const tenants = ref<BuildingTenant[]>([])
const correction = reactive({ lease_tenant_id: null as number | null, description: '', amount_cents: null as number | null, credit: false })
const correctionErrors = ref<ValidationErrors>({})

async function openCorrection() {
  correcting.value = true
  correctionErrors.value = {}
  if (tenants.value.length === 0) tenants.value = await buildingChargesApi.tenants(buildingId.value)
}

async function saveCorrection() {
  correctionErrors.value = {}
  try {
    const amount = Math.abs(correction.amount_cents ?? 0) * (correction.credit ? -1 : 1)
    await buildingChargesApi.create(buildingId.value, {
      lease_tenant_id: correction.lease_tenant_id ?? 0,
      description: correction.description,
      amount_cents: amount,
    })
    correcting.value = false
    Object.assign(correction, { lease_tenant_id: null, description: '', amount_cents: null, credit: false })
    await load()
  } catch (e) {
    correctionErrors.value = validationErrors(e)
    if (Object.keys(correctionErrors.value).length === 0) correctionErrors.value = { general: [errorMessage(e)] }
  }
}

const sourceCharge = ref<Charge | null>(null)
</script>

<template>
  <PageHeader title="Obveznosti" :eyebrow="auth.membership?.building.name">
    <template #actions>
      <button class="btn btn-secondary" @click="openCorrection">+ Popravek ali dobropis</button>
    </template>
  </PageHeader>

  <div class="card overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-line px-6 py-4">
      <FilterChips
        v-model="filters.status"
        :options="[
          { value: null, label: 'Vse' },
          { value: 'open', label: 'Odprte' },
          { value: 'overdue', label: 'Zamuja' },
          { value: 'paid', label: 'Plačane' },
        ]"
      />
      <div class="flex gap-3">
        <select v-model="filters.period" class="field-input w-44" aria-label="Mesec obračuna">
          <option value="">Vsi meseci</option>
          <option v-for="p in periods" :key="p" :value="p">{{ formatPeriod(`${p}-01`) }}</option>
        </select>
        <select v-model="filters.apartment_id" class="field-input w-44" aria-label="Stanovanje">
          <option :value="null">Vsa stanovanja</option>
          <option v-for="a in apartments" :key="a.id" :value="a.id">{{ a.label }}</option>
        </select>
      </div>
    </div>

    <table class="w-full text-[15px]">
      <thead class="border-b border-line">
        <tr>
          <th class="table-head">Stan.</th>
          <th class="table-head">Najemnik</th>
          <th class="table-head">Opis</th>
          <th class="table-head">Mesec</th>
          <th class="table-head">Rok</th>
          <th class="table-head">Stanje</th>
          <th class="table-head text-right">Znesek</th>
          <th class="table-head"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading"><td colspan="8" class="px-6 py-8 text-muted">Nalagam …</td></tr>
        <tr v-else-if="charges.length === 0"><td colspan="8" class="px-6 py-8 text-muted">Ni obveznosti za izbrane filtre.</td></tr>
        <tr
          v-for="charge in charges"
          :key="charge.id"
          class="border-b border-line last:border-0"
          :class="charge.status === 'overdue' ? 'bg-danger-row' : ''"
        >
          <td class="px-6 py-3.5 font-mono font-medium">{{ charge.apartment?.label }}</td>
          <td class="px-6 py-3.5">{{ charge.tenant?.name }}</td>
          <td class="px-6 py-3.5">
            <span class="text-muted first-letter:uppercase">{{ charge.type_label }} · </span>
            <button v-if="charge.has_source" class="underline" @click="sourceCharge = charge">{{ charge.description }}</button>
            <template v-else>{{ charge.description }}</template>
          </td>
          <td class="px-6 py-3.5">{{ formatPeriod(charge.period) }}</td>
          <td class="px-6 py-3.5 font-mono">{{ formatDate(charge.due_date) }}</td>
          <td class="px-6 py-3.5"><ChargeStatusChip :status="charge.status" :days-overdue="charge.days_overdue" /></td>
          <td class="px-6 py-3.5 text-right font-mono" :class="charge.status === 'overdue' ? 'font-semibold text-danger' : ''">
            {{ formatMoney(charge.amount_cents) }}
          </td>
          <td class="px-6 py-3.5 text-right">
            <button v-if="charge.status !== 'paid'" class="btn btn-secondary px-3 py-1.5 text-sm whitespace-nowrap" @click="openPay(charge)">
              Označi kot plačano
            </button>
            <span v-else class="font-mono text-sm text-muted">{{ formatDate(charge.paid_at) }}</span>
          </td>
        </tr>
      </tbody>
      <tfoot v-if="charges.length" class="border-t border-line">
        <tr>
          <td colspan="6" class="px-6 py-3.5 text-right text-muted">Skupaj ({{ charges.length }})</td>
          <td class="px-6 py-3.5 text-right font-mono font-semibold">{{ formatMoney(total) }}</td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <BaseModal v-if="paying" eyebrow="Plačilo" title="Označi kot plačano" @close="paying = null">
    <p class="text-[15px]">
      {{ paying.tenant?.name }} · {{ paying.description }} ·
      <strong class="font-mono">{{ formatMoney(paying.amount_cents) }}</strong>
    </p>
    <label class="field-label mt-5" for="paid_on">Datum plačila</label>
    <input id="paid_on" v-model="paidOn" type="date" :max="todayIso()" class="field-input font-mono" />
    <p v-if="payError" class="field-error">{{ payError }}</p>
    <p class="mt-3 text-sm text-muted">Aplikacija plačil ne izvaja, zabeleži se samo prejem.</p>
    <div class="mt-6 flex justify-end gap-3">
      <button class="btn btn-secondary" @click="paying = null">Prekliči</button>
      <button class="btn btn-primary" @click="confirmPaid">Potrdi plačilo</button>
    </div>
  </BaseModal>

  <BaseModal v-if="correcting" eyebrow="Nova obveznost vrste drugo" title="Popravek ali dobropis" @close="correcting = false">
    <p class="text-sm text-muted">
      Izdanih obveznosti ni mogoče urejati ali brisati. Popravek vnesite kot novo obveznost; dobropis zmanjša dolg najemnika.
    </p>
    <label class="field-label mt-5" for="tenant">Najemnik</label>
    <select id="tenant" v-model="correction.lease_tenant_id" class="field-input">
      <option :value="null" disabled>Izberite najemnika</option>
      <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.apartment }} · {{ t.name }}</option>
    </select>
    <p v-if="correctionErrors.lease_tenant_id" class="field-error">{{ correctionErrors.lease_tenant_id[0] }}</p>

    <label class="field-label mt-4" for="description">Opis</label>
    <input id="description" v-model="correction.description" class="field-input" />
    <p v-if="correctionErrors.description" class="field-error">{{ correctionErrors.description[0] }}</p>

    <div class="mt-4 grid grid-cols-2 gap-4">
      <div>
        <label class="field-label" for="amount">Znesek</label>
        <MoneyInput id="amount" v-model="correction.amount_cents" />
      </div>
      <div>
        <span class="field-label">Vrsta</span>
        <label class="flex items-center gap-2 py-1 text-[15px]"><input v-model="correction.credit" type="radio" :value="false" /> Bremepis</label>
        <label class="flex items-center gap-2 py-1 text-[15px]"><input v-model="correction.credit" type="radio" :value="true" /> Dobropis</label>
      </div>
    </div>
    <p v-if="correctionErrors.amount_cents" class="field-error">{{ correctionErrors.amount_cents[0] }}</p>
    <p v-if="correctionErrors.general" class="field-error">{{ correctionErrors.general[0] }}</p>

    <div class="mt-6 flex justify-end gap-3">
      <button class="btn btn-secondary" @click="correcting = false">Prekliči</button>
      <button class="btn btn-primary" @click="saveCorrection">Dodaj obveznost</button>
    </div>
  </BaseModal>

  <ChargeSourceModal v-if="sourceCharge" :charge="sourceCharge" @close="sourceCharge = null" />
</template>
