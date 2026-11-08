<script setup lang="ts">
import { AxiosError } from 'axios'
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import PageHeader from '@/components/PageHeader.vue'
import LeaseStatusChip from '@/components/LeaseStatusChip.vue'
import { floorLabel, leasesApi, type Lease } from '@/api/leases'
import { errorMessage } from '@/api/http'
import { formatDate, formatMoney } from '@/utils/format'

const route = useRoute()
const lease = ref<Lease | null>(null)
const busy = ref(false)
const conflict = ref<{ message: string; leaseId: number | null } | null>(null)

async function load() {
  conflict.value = null
  lease.value = await leasesApi.get(Number(route.params.id))
}

onMounted(load)
watch(() => route.params.id, load)

async function activate() {
  busy.value = true
  conflict.value = null
  try {
    lease.value = await leasesApi.activate(lease.value!.id)
  } catch (e) {
    const data = e instanceof AxiosError ? e.response?.data : null
    conflict.value = { message: errorMessage(e), leaseId: data?.conflicting_lease_id ?? null }
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <template v-if="lease">
    <PageHeader
      :title="`Najem · ${lease.apartment?.label}`"
      :crumbs="[{ label: 'Najemi', to: '/najemi' }, { label: `N-${String(lease.id).padStart(4, '0')}` }]"
    >
      <template #badge><LeaseStatusChip :status="lease.status" :label="lease.status_label" /></template>
      <template #meta>
        <p class="mt-2 text-muted">
          Stanovanje <strong class="text-ink">{{ lease.apartment?.label }}</strong> ·
          {{ floorLabel(lease.apartment?.floor ?? 0) === 'P' ? 'pritličje' : `${lease.apartment?.floor}. nadstropje` }}
        </p>
      </template>
    </PageHeader>

    <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
      <div class="space-y-6">
        <section class="card p-8">
          <h2 class="text-2xl font-semibold">Pogoji najema</h2>
          <dl class="mt-5 grid gap-x-8 gap-y-4 text-[15px] md:grid-cols-3">
            <div>
              <dt class="text-muted">Začetek</dt>
              <dd class="font-mono">{{ formatDate(lease.starts_on) }}</dd>
            </div>
            <div>
              <dt class="text-muted">Konec</dt>
              <dd class="font-mono">{{ lease.ends_on ? formatDate(lease.ends_on) : 'nedoločen čas' }}</dd>
            </div>
            <div>
              <dt class="text-muted">Odpovedni rok</dt>
              <dd class="font-mono">{{ lease.notice_period_days }} dni</dd>
            </div>
            <div>
              <dt class="text-muted">Mesečna najemnina</dt>
              <dd class="font-mono">{{ formatMoney(lease.monthly_rent_cents) }}</dd>
            </div>
            <div>
              <dt class="text-muted">Varščina</dt>
              <dd class="font-mono">{{ formatMoney(lease.deposit_cents) }}</dd>
            </div>
            <div v-if="lease.terminates_on">
              <dt class="text-muted">Prenehanje</dt>
              <dd class="font-mono">{{ formatDate(lease.terminates_on) }}</dd>
            </div>
          </dl>
        </section>

        <section class="card overflow-hidden">
          <h2 class="px-8 pt-7 text-2xl font-semibold">Najemniki</h2>
          <table class="mt-4 w-full text-[15px]">
            <thead class="border-y border-line">
              <tr>
                <th class="table-head pl-8">Ime</th>
                <th class="table-head">E-naslov</th>
                <th class="table-head">Telefon</th>
                <th class="table-head pr-8 text-right">Delež</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in lease.tenants" :key="t.id" class="border-b border-line last:border-0">
                <td class="px-6 py-4 pl-8">{{ t.name }}</td>
                <td class="px-6 py-4">{{ t.email }}</td>
                <td class="px-6 py-4">{{ t.phone ?? '—' }}</td>
                <td class="px-6 py-4 pr-8 text-right font-mono">{{ formatMoney(t.rent_share_cents) }}</td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>

      <aside v-if="lease.status === 'draft'" class="card-strong h-fit p-7">
        <p class="eyebrow">Vaš korak</p>
        <h2 class="mt-1 text-2xl font-semibold">Aktiviraj najem</h2>
        <p class="mt-3 text-[15px] text-muted">
          Aktiven najem zaseda stanovanje. Sistem bo začel ustvarjati mesečne obveznosti za najemnino.
        </p>
        <div v-if="conflict" class="mt-4 rounded-lg bg-danger-soft p-4 text-[15px] text-danger">
          {{ conflict.message }}
          <RouterLink v-if="conflict.leaseId" :to="`/najemi/${conflict.leaseId}`" class="mt-1 block font-semibold underline">
            Odpri obstoječi najem
          </RouterLink>
        </div>
        <button class="btn btn-primary mt-6 w-full" :disabled="busy" @click="activate">Aktiviraj</button>
      </aside>
    </div>
  </template>
</template>
