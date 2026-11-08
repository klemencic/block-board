<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import PageHeader from '@/components/PageHeader.vue'
import MoneyInput from '@/components/MoneyInput.vue'
import AppIcon from '@/components/AppIcon.vue'
import {
  billsApi,
  splitMethods,
  utilityTypes,
  type BillPayload,
  type BillPreview,
  type SplitMethod,
  type UtilityBill,
  type UtilityType,
} from '@/api/bills'
import { floorLabel, leasesApi, type Apartment } from '@/api/leases'
import { errorMessage, validationErrors, type ValidationErrors } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { formatDate, formatMoney, monthName } from '@/utils/format'
import { inclusiveDays, residenceBar } from '@/utils/period'

// Wireframe WF-04 »Nova položnica« (US-06, US-07).
const BUILDING_SCOPE_ENABLED = true

const auth = useAuthStore()
const buildingId = computed(() => auth.buildingId!)

const apartments = ref<Apartment[]>([])
const bills = ref<UtilityBill[]>([])
const preview = ref<BillPreview | null>(null)
const errors = ref<ValidationErrors>({})
const saveError = ref<string | null>(null)
const saved = ref<string | null>(null)
const busy = ref(false)

const form = reactive({
  scope: 'apartment' as 'apartment' | 'building',
  apartment_id: null as number | null,
  type: 'electricity' as UtilityType,
  amount_cents: null as number | null,
  period_start: '',
  period_end: '',
  split_method: null as SplitMethod | null,
})

onMounted(async () => {
  ;[apartments.value, bills.value] = await Promise.all([
    leasesApi.apartments(buildingId.value),
    billsApi.list(buildingId.value),
  ])
})

const payload = computed<BillPayload>(() => ({
  scope: form.scope,
  apartment_id: form.scope === 'apartment' ? form.apartment_id : null,
  type: form.type,
  amount_cents: form.amount_cents ?? 0,
  period_start: form.period_start,
  period_end: form.period_end,
  split_method: form.scope === 'building' ? form.split_method : null,
}))

const ready = computed(
  () =>
    !!form.amount_cents &&
    !!form.period_start &&
    !!form.period_end &&
    (form.scope === 'apartment' ? !!form.apartment_id : !!form.split_method),
)

// Pri položnici za celo stavbo je privzeta delitev po površini.
watch(
  () => form.scope,
  (scope) => {
    if (scope === 'building' && !form.split_method) form.split_method = 'area'
  },
)

const periodDays = computed(() =>
  form.period_start && form.period_end && form.period_end >= form.period_start
    ? inclusiveDays(form.period_start, form.period_end)
    : null,
)

// Predogled se osveži ob vsaki spremembi obrazca.
let timer: ReturnType<typeof setTimeout> | undefined
watch(
  payload,
  () => {
    clearTimeout(timer)
    saved.value = null
    if (!ready.value) {
      preview.value = null
      return
    }
    timer = setTimeout(async () => {
      try {
        errors.value = {}
        preview.value = await billsApi.preview(buildingId.value, payload.value)
      } catch (e) {
        preview.value = null
        errors.value = validationErrors(e)
      }
    }, 300)
  },
  { deep: true },
)

const previewTitle = computed(() => {
  const type = utilityTypes.find((t) => t.value === form.type)?.label
  const scope = form.scope === 'apartment' ? apartments.value.find((a) => a.id === form.apartment_id)?.label : 'cela stavba'
  const month = form.period_start ? monthName(Number(form.period_start.slice(5, 7))) : ''
  return [type, scope, month].filter(Boolean).join(' · ')
})

async function save() {
  busy.value = true
  saveError.value = null
  try {
    await billsApi.store(buildingId.value, payload.value)
    saved.value = 'Položnica je shranjena, obveznosti so ustvarjene.'
    bills.value = await billsApi.list(buildingId.value)
    preview.value = null
    Object.assign(form, { amount_cents: null, period_start: '', period_end: '' })
  } catch (e) {
    errors.value = validationErrors(e)
    saveError.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}

function reset() {
  preview.value = null
  Object.assign(form, { apartment_id: null, amount_cents: null, period_start: '', period_end: '', split_method: null })
}
</script>

<template>
  <PageHeader title="Nova položnica" :crumbs="[{ label: 'Položnice' }, { label: 'Nova položnica' }]" />

  <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)]">
    <!-- Podatki položnice -->
    <section class="card h-fit p-8">
      <h2 class="text-2xl font-semibold">Podatki položnice</h2>

      <span class="field-label mt-6">Velja za</span>
      <div class="grid grid-cols-2 gap-1 rounded-xl bg-subtle p-1.5">
        <label
          v-for="option in [
            { value: 'building', label: 'Celo stavbo' },
            { value: 'apartment', label: 'Eno stanovanje' },
          ]"
          :key="option.value"
          class="flex cursor-pointer items-center justify-center gap-2 rounded-lg py-2.5 text-[15px]"
          :class="[
            form.scope === option.value ? 'bg-surface font-semibold shadow-sm' : 'text-muted',
            option.value === 'building' && !BUILDING_SCOPE_ENABLED ? 'cursor-not-allowed opacity-50' : '',
          ]"
        >
          <input
            v-model="form.scope"
            type="radio"
            :value="option.value"
            :disabled="option.value === 'building' && !BUILDING_SCOPE_ENABLED"
            class="accent-primary"
          />
          {{ option.label }}
        </label>
      </div>

      <div class="mt-5 grid gap-5 md:grid-cols-2">
        <div v-if="form.scope === 'apartment'">
          <label class="field-label" for="apartment">Stanovanje</label>
          <select id="apartment" v-model="form.apartment_id" class="field-input">
            <option :value="null" disabled>Izberite</option>
            <option v-for="a in apartments" :key="a.id" :value="a.id">
              {{ a.label }} · {{ floorLabel(a.floor) === 'P' ? 'pritličje' : `${a.floor}. nad.` }}
            </option>
          </select>
          <p v-if="errors.apartment_id" class="field-error">{{ errors.apartment_id[0] }}</p>
        </div>
        <div :class="form.scope === 'building' ? 'md:col-span-2' : ''">
          <label class="field-label" for="type">Vrsta</label>
          <select id="type" v-model="form.type" class="field-input">
            <option v-for="t in utilityTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>
      </div>

      <label class="field-label mt-5" for="amount">Znesek</label>
      <MoneyInput id="amount" v-model="form.amount_cents" />
      <p v-if="errors.amount_cents" class="field-error">{{ errors.amount_cents[0] }}</p>

      <div class="mt-5 grid gap-5 md:grid-cols-2">
        <div>
          <label class="field-label" for="from">Obdobje od</label>
          <input id="from" v-model="form.period_start" type="date" class="field-input font-mono" />
        </div>
        <div>
          <label class="field-label" for="to">Obdobje do</label>
          <input id="to" v-model="form.period_end" type="date" class="field-input font-mono" />
        </div>
      </div>
      <p v-if="errors.period_end" class="field-error">{{ errors.period_end[0] }}</p>
      <p v-else-if="periodDays" class="mt-2 text-sm text-muted">
        {{ periodDays }} dni · štejeta se dan vselitve in dan izselitve
      </p>

      <fieldset
        class="mt-6 rounded-xl border border-dashed border-line px-5 pt-2 pb-4"
        :disabled="form.scope !== 'building'"
        :class="form.scope !== 'building' ? 'opacity-60' : ''"
      >
        <legend class="px-2 font-semibold">Delitev med stanovanja</legend>
        <div class="flex flex-wrap gap-5">
          <label v-for="m in splitMethods" :key="m.value" class="flex items-center gap-2 text-[15px]">
            <input v-model="form.split_method" type="radio" :value="m.value" class="accent-primary" /> {{ m.label }}
          </label>
        </div>
        <p v-if="form.scope !== 'building'" class="mt-2 text-sm text-muted">Samo pri položnici za celo stavbo.</p>
        <p v-if="errors.split_method" class="field-error">{{ errors.split_method[0] }}</p>
      </fieldset>
    </section>

    <!-- Predogled deležev -->
    <section class="card-strong h-fit overflow-hidden">
      <div class="flex items-end justify-between gap-4 px-7 pt-7">
        <div>
          <p class="eyebrow">Predogled deležev</p>
          <h2 class="mt-1 text-[28px] font-semibold">{{ previewTitle || 'Izpolnite podatke' }}</h2>
        </div>
        <p class="text-sm text-muted">{{ form.scope === 'building' ? 'po stanovanjih in dnevih' : 'po dnevih bivanja' }}</p>
      </div>

      <p v-if="!preview" class="px-7 py-10 text-muted">
        Predogled se prikaže, ko vnesete stanovanje, znesek in obdobje.
      </p>

      <template v-else>
        <table class="mt-5 w-full text-[15px]">
          <thead class="border-y border-line">
            <tr>
              <th class="table-head pl-7">{{ form.scope === 'building' ? 'Stanovanje / sostanovalec' : 'Sostanovalec' }}</th>
              <th class="table-head w-1/3">Bival v obdobju</th>
              <th class="table-head text-right">Dni</th>
              <th class="table-head pr-7 text-right">Delež</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="apartment in preview.apartments" :key="apartment.id">
              <tr v-if="form.scope === 'building'" class="border-b border-line bg-canvas">
                <td colspan="3" class="px-6 py-2.5 pl-7 font-semibold">
                  {{ apartment.label }}
                  <span class="font-normal text-muted">· {{ apartment.area_m2 }} m²</span>
                </td>
                <td class="px-6 py-2.5 pr-7 text-right font-mono font-semibold">{{ formatMoney(apartment.amount_cents) }}</td>
              </tr>
              <tr v-for="(share, i) in apartment.shares" :key="`${apartment.id}-${i}`" class="border-b border-line">
                <td class="px-6 py-3.5 pl-7">
                  <p :class="share.lease_tenant_id ? 'font-medium' : 'text-muted italic'">{{ share.name }}</p>
                  <p v-if="share.lease_tenant_id" class="text-sm text-muted">
                    <template v-if="share.moved_in_on && share.moved_in_on > form.period_start">
                      vselitev {{ formatDate(share.moved_in_on).slice(0, -5) }}
                    </template>
                    <template v-else-if="share.moved_out_on && share.moved_out_on < form.period_end">
                      izselitev {{ formatDate(share.moved_out_on).slice(0, -5) }}
                    </template>
                    <template v-else>celo obdobje</template>
                  </p>
                </td>
                <td class="px-6 py-3.5">
                  <div v-if="share.lease_tenant_id" class="relative h-4 rounded-sm bg-[repeating-linear-gradient(135deg,#e7e5df_0_6px,#f1efea_6px_12px)]">
                    <div
                      class="absolute inset-y-0 rounded-sm bg-primary"
                      :style="{
                        left: `${residenceBar(form.period_start, form.period_end, share.moved_in_on, share.moved_out_on).offset}%`,
                        width: `${residenceBar(form.period_start, form.period_end, share.moved_in_on, share.moved_out_on).width}%`,
                      }"
                    />
                  </div>
                </td>
                <td class="px-6 py-3.5 text-right font-mono">{{ share.days }}</td>
                <td class="px-6 py-3.5 pr-7 text-right font-mono font-semibold">{{ formatMoney(share.amount_cents) }}</td>
              </tr>
            </template>
          </tbody>
        </table>

        <div class="mx-7 flex items-center justify-between border-b border-line py-5">
          <p v-if="preview.matches_bill" class="flex items-center gap-2 text-success">
            <AppIcon name="check" :size="18" /> Vsota se ujema s položnico do centa
          </p>
          <p v-else class="text-danger">Vsota se ne ujema s položnico.</p>
          <p class="text-muted">
            Skupaj <span class="ml-2 font-display text-[34px] font-semibold text-ink">{{ formatMoney(preview.total_cents) }}</span>
          </p>
        </div>

        <div class="px-7 py-5">
          <p class="text-[15px] text-muted">
            Ob shranjevanju vsak sostanovalec dobi obveznost vrste »stroški« k obračunu za
            {{ preview.charge_period_label }}. Položnica, deleži in obveznosti se shranijo skupaj ali pa nič.
          </p>
          <p v-if="saveError" class="field-error">{{ saveError }}</p>
          <div class="mt-5 flex justify-end gap-3">
            <button class="btn btn-secondary" @click="reset">Prekliči</button>
            <button class="btn btn-primary" :disabled="busy || !preview.matches_bill" @click="save">
              Shrani in ustvari obveznosti
            </button>
          </div>
        </div>
      </template>

      <p v-if="saved" class="mx-7 mb-7 rounded-lg bg-[#e7f4ec] p-4 text-success">{{ saved }}</p>
    </section>
  </div>

  <!-- Zadnje položnice -->
  <section class="card mt-8 overflow-hidden">
    <h2 class="px-7 pt-6 pb-4 text-2xl font-semibold">Zadnje položnice</h2>
    <table class="w-full text-[15px]">
      <thead class="border-y border-line">
        <tr>
          <th class="table-head pl-7">Vrsta</th>
          <th class="table-head">Velja za</th>
          <th class="table-head">Obdobje</th>
          <th class="table-head">Delitev</th>
          <th class="table-head pr-7 text-right">Znesek</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="bills.length === 0"><td colspan="5" class="px-7 py-6 text-muted">Ni še vnesenih položnic.</td></tr>
        <tr v-for="bill in bills" :key="bill.id" class="border-b border-line last:border-0">
          <td class="px-6 py-4 pl-7">{{ bill.type_label }}</td>
          <td class="px-6 py-4">{{ bill.scope === 'building' ? 'Cela stavba' : `Stanovanje ${bill.apartment?.label}` }}</td>
          <td class="px-6 py-4 font-mono">{{ formatDate(bill.period_start) }} – {{ formatDate(bill.period_end) }}</td>
          <td class="px-6 py-4">{{ bill.split_method_label ?? 'po dnevih bivanja' }}</td>
          <td class="px-6 py-4 pr-7 text-right font-mono">{{ formatMoney(bill.amount_cents) }}</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>
