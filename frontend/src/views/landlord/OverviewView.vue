<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import StatusChip from '@/components/StatusChip.vue'
import FilterChips from '@/components/FilterChips.vue'
import { filterAndSort, overviewApi, type ApartmentState, type Overview, type SortKey } from '@/api/overview'
import { floorLabel } from '@/api/leases'
import { useAuthStore } from '@/stores/auth'
import { formatDate, formatMoney } from '@/utils/format'
import { parseDate } from '@/utils/calendar'

// Wireframe WF-01 »Pregled stavbe« (US-13).
const auth = useAuthStore()
const router = useRouter()
const data = ref<Overview | null>(null)
const state = ref<ApartmentState | null>(null)
const sort = ref<SortKey>('label')

onMounted(async () => {
  data.value = await overviewApi.get(auth.buildingId!)
})

const rows = computed(() => (data.value ? filterAndSort(data.value.apartments, state.value, sort.value) : []))

const DAY_FULL = ['Ponedeljek', 'Torek', 'Sreda', 'Četrtek', 'Petek', 'Sobota', 'Nedelja']
const dateLabel = computed(() => {
  if (!data.value) return ''
  const d = parseDate(data.value.date)
  return `${DAY_FULL[(d.getDay() + 6) % 7]}, ${formatDate(data.value.date)}`
})

const stateChip: Record<ApartmentState, { tone: 'neutral' | 'warning' | 'dashed'; label: string }> = {
  occupied: { tone: 'neutral', label: 'Zasedeno' },
  in_notice: { tone: 'warning', label: 'V odpovednem roku' },
  vacant: { tone: 'dashed', label: 'Prosto' },
}
</script>

<template>
  <template v-if="data">
    <header class="mb-8 flex flex-wrap items-end justify-between gap-6">
      <div>
        <p class="mb-1 font-mono text-sm text-muted">{{ dateLabel }}</p>
        <h1 class="text-[44px] leading-tight font-semibold tracking-tight">Pregled stavbe</h1>
      </div>
      <div class="flex gap-3">
        <RouterLink to="/poloznice" class="btn btn-secondary px-6 py-3 text-[17px]">Vnesi položnico</RouterLink>
        <RouterLink to="/najemi/nov" class="btn btn-primary px-6 py-3 text-[17px]">+ Nov najem</RouterLink>
      </div>
    </header>

    <!-- Povzetek -->
    <div class="mb-8 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
      <div class="card p-6">
        <p class="text-muted">Zasedena stanovanja</p>
        <p class="mt-3 font-display text-[44px] leading-none font-semibold">
          {{ data.summary.apartments_occupied }}<span class="text-xl font-normal text-muted"> / {{ data.summary.apartments_total }}</span>
        </p>
        <div class="mt-5 flex gap-1">
          <span
            v-for="a in data.apartments"
            :key="a.id"
            class="h-1.5 flex-1 rounded-sm"
            :class="a.state === 'vacant' ? 'bg-subtle' : 'bg-ink'"
            :title="a.label"
          />
        </div>
      </div>

      <div class="rounded-xl border p-6" :class="data.summary.overdue_cents > 0 ? 'border-danger/30 bg-danger-soft' : 'card'">
        <p :class="data.summary.overdue_cents > 0 ? 'text-danger' : 'text-muted'">Zamude skupaj</p>
        <p class="mt-3 font-display text-[44px] leading-none font-semibold" :class="data.summary.overdue_cents > 0 ? 'text-danger' : ''">
          {{ formatMoney(data.summary.overdue_cents) }}
        </p>
        <p class="mt-5" :class="data.summary.overdue_cents > 0 ? 'text-danger' : 'text-muted'">
          {{ data.summary.overdue_apartments }} {{ data.summary.overdue_apartments === 2 ? 'stanovanji' : data.summary.overdue_apartments === 1 ? 'stanovanje' : 'stanovanj' }}
          · {{ data.summary.overdue_charges }} {{ data.summary.overdue_charges === 1 ? 'obveznost' : data.summary.overdue_charges === 2 ? 'obveznosti' : 'obveznosti' }}
        </p>
      </div>

      <RouterLink to="/okvare" class="card block p-6 hover:border-ink/30">
        <p class="text-muted">Odprte prijave okvar</p>
        <p class="mt-3 font-display text-[44px] leading-none font-semibold">{{ data.summary.open_repairs }}</p>
        <p class="mt-5 text-muted">{{ data.summary.repairs_awaiting_confirmation }} čaka na potrditev</p>
      </RouterLink>

      <div class="card p-6">
        <p class="text-muted">Najemi, ki se iztekajo</p>
        <p class="mt-3 font-display text-[44px] leading-none font-semibold">{{ data.summary.expiring_leases }}</p>
        <p class="mt-5 text-muted">v naslednjih 60 dneh</p>
      </div>
    </div>

    <!-- Tabela stanovanj -->
    <div class="card overflow-hidden">
      <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
        <FilterChips
          v-model="state"
          :options="[
            { value: null, label: 'Vsa', count: data.summary.apartments_total },
            { value: 'occupied', label: 'Zasedeno', count: data.summary.counts.occupied },
            { value: 'in_notice', label: 'V odpovednem roku', count: data.summary.counts.in_notice },
            { value: 'vacant', label: 'Prosto', count: data.summary.counts.vacant },
          ]"
        />
        <label class="flex items-center gap-3 text-muted">
          Razvrsti
          <select v-model="sort" class="field-input w-52 text-ink">
            <option value="label">Po oznaki</option>
            <option value="overdue">Po zamudah</option>
          </select>
        </label>
      </div>
      <table class="w-full text-[16px]">
        <thead class="border-y border-line">
          <tr>
            <th class="table-head">Stan.</th>
            <th class="table-head">Nad.</th>
            <th class="table-head">Stanje</th>
            <th class="table-head">Najemniki</th>
            <th class="table-head">Konec / prenehanje</th>
            <th class="table-head text-right">Zamude</th>
            <th class="table-head text-right">Okvare</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            class="cursor-pointer border-b border-line last:border-0 hover:bg-canvas"
            :class="row.overdue_cents > 0 ? 'bg-danger-row' : ''"
            @click="router.push(`/stanovanja/${row.id}`)"
          >
            <td class="px-6 py-4 font-mono font-semibold">{{ row.label }}</td>
            <td class="px-6 py-4">{{ floorLabel(row.floor) }}</td>
            <td class="px-6 py-4"><StatusChip :tone="stateChip[row.state].tone">{{ stateChip[row.state].label }}</StatusChip></td>
            <td class="px-6 py-4">{{ row.tenants.length ? row.tenants.join(', ') : '—' }}</td>
            <td class="px-6 py-4">
              <span v-if="row.end_type === 'indefinite'" class="text-muted">nedoločen čas</span>
              <template v-else-if="row.end_type === 'termination'">prenehanje {{ formatDate(row.end_date) }}</template>
              <template v-else-if="row.end_date">
                {{ formatDate(row.end_date) }}
                <span v-if="row.expiring" class="ml-2 rounded bg-primary-soft px-2 py-0.5 text-sm font-semibold text-primary">Izteka se</span>
              </template>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="px-6 py-4 text-right font-mono" :class="row.overdue_cents > 0 ? 'font-semibold text-danger' : 'text-muted'">
              {{ row.overdue_cents > 0 ? formatMoney(row.overdue_cents) : '—' }}
            </td>
            <td class="px-6 py-4 text-right font-mono" :class="row.open_repairs ? 'font-semibold' : 'text-muted'">{{ row.open_repairs }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </template>
</template>
