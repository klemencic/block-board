<script setup lang="ts">
import { onMounted, ref } from 'vue'
import PageHeader from '@/components/PageHeader.vue'
import LeaseStatusChip from '@/components/LeaseStatusChip.vue'
import { leasesApi, type Lease } from '@/api/leases'
import { useAuthStore } from '@/stores/auth'
import { formatDate, formatMoney } from '@/utils/format'

const auth = useAuthStore()
const leases = ref<Lease[]>([])
const loading = ref(true)

onMounted(async () => {
  leases.value = await leasesApi.list(auth.buildingId!)
  loading.value = false
})
</script>

<template>
  <PageHeader title="Najemi" :eyebrow="auth.membership?.building.name">
    <template #actions>
      <RouterLink to="/najemi/nov" class="btn btn-primary">+ Nov najem</RouterLink>
    </template>
  </PageHeader>

  <div class="card overflow-hidden">
    <table class="w-full text-[15px]">
      <thead class="border-b border-line">
        <tr>
          <th class="table-head">Stan.</th>
          <th class="table-head">Stanje</th>
          <th class="table-head">Najemniki</th>
          <th class="table-head">Začetek</th>
          <th class="table-head">Konec / prenehanje</th>
          <th class="table-head text-right">Najemnina</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading"><td colspan="6" class="px-6 py-8 text-muted">Nalagam …</td></tr>
        <tr v-else-if="leases.length === 0">
          <td colspan="6" class="px-6 py-8 text-muted">V stavbi še ni najemov.</td>
        </tr>
        <tr
          v-for="lease in leases"
          :key="lease.id"
          class="cursor-pointer border-b border-line last:border-0 hover:bg-canvas"
          @click="$router.push(`/najemi/${lease.id}`)"
        >
          <td class="px-6 py-4 font-mono font-medium">{{ lease.apartment?.label }}</td>
          <td class="px-6 py-4"><LeaseStatusChip :status="lease.status" :label="lease.status_label" /></td>
          <td class="px-6 py-4">{{ lease.tenants?.map((t) => t.name).join(', ') }}</td>
          <td class="px-6 py-4">{{ formatDate(lease.starts_on) }}</td>
          <td class="px-6 py-4 text-muted">
            <template v-if="lease.terminates_on">prenehanje {{ formatDate(lease.terminates_on) }}</template>
            <template v-else-if="lease.ends_on">{{ formatDate(lease.ends_on) }}</template>
            <template v-else>nedoločen čas</template>
          </td>
          <td class="px-6 py-4 text-right">{{ formatMoney(lease.monthly_rent_cents) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
