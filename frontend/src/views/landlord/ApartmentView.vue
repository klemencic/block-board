<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import PageHeader from '@/components/PageHeader.vue'
import LeaseStatusChip from '@/components/LeaseStatusChip.vue'
import ChargeStatusChip from '@/components/ChargeStatusChip.vue'
import { overviewApi, type ApartmentDetail } from '@/api/overview'
import { formatDate, formatMoney, formatPeriod } from '@/utils/format'

// Podrobnosti stanovanja ob kliku na vrstico pregleda stavbe (US-13, #130).
const route = useRoute()
const apartment = ref<ApartmentDetail | null>(null)

onMounted(async () => {
  apartment.value = await overviewApi.apartment(Number(route.params.id))
})
</script>

<template>
  <template v-if="apartment">
    <PageHeader
      :title="`Stanovanje ${apartment.label}`"
      :crumbs="[{ label: 'Pregled stavbe', to: '/pregled' }, { label: apartment.label }]"
    >
      <template #meta>
        <p class="mt-2 text-muted">
          {{ apartment.floor === 0 ? 'pritličje' : `${apartment.floor}. nadstropje` }} · {{ apartment.area_m2 }} m² ·
          {{ apartment.rooms }} {{ apartment.rooms === 1 ? 'soba' : apartment.rooms === 2 ? 'sobi' : 'sobe' }}
        </p>
      </template>
      <template #actions>
        <RouterLink v-if="!apartment.current_lease" :to="`/najemi/nov?apartment=${apartment.id}`" class="btn btn-primary">
          + Nov najem
        </RouterLink>
      </template>
    </PageHeader>

    <div class="grid gap-6 xl:grid-cols-2">
      <section class="card p-7">
        <h2 class="text-2xl font-semibold">Trenutni najem</h2>
        <template v-if="apartment.current_lease">
          <p class="mt-3"><LeaseStatusChip :status="apartment.current_lease.status" :label="apartment.current_lease.status_label" /></p>
          <p class="mt-3 text-[16px]">{{ apartment.current_lease.tenants?.map((t) => t.name).join(', ') }}</p>
          <p class="mt-1 text-muted">
            od {{ formatDate(apartment.current_lease.starts_on) }}
            <template v-if="apartment.current_lease.terminates_on"> · prenehanje {{ formatDate(apartment.current_lease.terminates_on) }}</template>
            <template v-else-if="apartment.current_lease.ends_on"> · do {{ formatDate(apartment.current_lease.ends_on) }}</template>
            <template v-else> · nedoločen čas</template>
          </p>
          <RouterLink :to="`/najemi/${apartment.current_lease.id}`" class="btn btn-secondary mt-5">Odpri najem</RouterLink>
        </template>
        <p v-else class="mt-3 text-muted">Stanovanje je prosto.</p>
      </section>

      <section class="card p-7">
        <h2 class="text-2xl font-semibold">Odprte prijave okvar</h2>
        <p v-if="!apartment.open_repairs.length" class="mt-3 text-muted">Ni odprtih prijav.</p>
        <ul class="mt-3 divide-y divide-line">
          <li v-for="r in apartment.open_repairs" :key="r.id" class="py-3">
            <RouterLink :to="`/okvare/${r.id}`" class="flex justify-between gap-3 hover:underline">
              <span><span class="font-mono text-muted">{{ r.number }}</span> {{ r.title }}</span>
              <span class="text-muted">{{ r.status_label }}</span>
            </RouterLink>
          </li>
        </ul>
      </section>

      <section class="card overflow-hidden xl:col-span-2">
        <h2 class="px-7 pt-6 pb-4 text-2xl font-semibold">Zamujene obveznosti</h2>
        <p v-if="!apartment.overdue_charges.length" class="px-7 pb-6 text-muted">Ni zamujenih obveznosti.</p>
        <table v-else class="w-full text-[15px]">
          <tbody>
            <tr v-for="c in apartment.overdue_charges" :key="c.id" class="border-t border-line bg-danger-row">
              <td class="px-7 py-3.5">{{ c.tenant?.name }}</td>
              <td class="px-6 py-3.5">{{ c.description }}</td>
              <td class="px-6 py-3.5">{{ formatPeriod(c.period) }}</td>
              <td class="px-6 py-3.5"><ChargeStatusChip :status="c.status" :days-overdue="c.days_overdue" /></td>
              <td class="px-7 py-3.5 text-right font-mono font-semibold text-danger">{{ formatMoney(c.amount_cents) }}</td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>
  </template>
</template>
