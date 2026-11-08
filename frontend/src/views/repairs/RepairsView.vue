<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusChip from '@/components/StatusChip.vue'
import FilterChips from '@/components/FilterChips.vue'
import { repairsApi, statusTone, type Repair } from '@/api/repairs'
import { useAuthStore } from '@/stores/auth'
import { formatDateTime } from '@/utils/format'

const auth = useAuthStore()
const repairs = ref<Repair[]>([])
const loading = ref(true)
const filter = ref<'open' | 'all'>('open')

onMounted(async () => {
  repairs.value = await repairsApi.list(auth.buildingId!)
  loading.value = false
})

const visible = computed(() =>
  filter.value === 'all' ? repairs.value : repairs.value.filter((r) => !['closed', 'rejected'].includes(r.status)),
)
const title = computed(() => (auth.role === 'tenant' ? 'Prijave okvar' : auth.role === 'contractor' ? 'Moji nalogi' : 'Okvare'))
</script>

<template>
  <PageHeader :title="title" :eyebrow="auth.membership?.building.name">
    <template #actions>
      <RouterLink v-if="auth.role !== 'contractor'" to="/okvare/nova" class="btn btn-primary">+ Prijavi okvaro</RouterLink>
    </template>
  </PageHeader>

  <div class="card overflow-hidden">
    <div class="border-b border-line px-6 py-4">
      <FilterChips
        v-model="filter"
        :options="[
          { value: 'open', label: 'Odprte', count: repairs.filter((r) => !['closed', 'rejected'].includes(r.status)).length },
          { value: 'all', label: 'Vse', count: repairs.length },
        ]"
      />
    </div>
    <table class="w-full text-[15px]">
      <thead class="border-b border-line">
        <tr>
          <th class="table-head">Št.</th>
          <th class="table-head">Okvara</th>
          <th class="table-head">Lokacija</th>
          <th class="table-head">Vrsta</th>
          <th class="table-head">Stanje</th>
          <th class="table-head">Prijavljeno</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading"><td colspan="6" class="px-6 py-8 text-muted">Nalagam …</td></tr>
        <tr v-else-if="visible.length === 0"><td colspan="6" class="px-6 py-8 text-muted">Ni prijav.</td></tr>
        <tr
          v-for="repair in visible"
          :key="repair.id"
          class="cursor-pointer border-b border-line last:border-0 hover:bg-canvas"
          @click="$router.push(`/okvare/${repair.id}`)"
        >
          <td class="px-6 py-4 font-mono text-muted">{{ repair.number }}</td>
          <td class="px-6 py-4 font-medium">{{ repair.title }}</td>
          <td class="px-6 py-4">
            {{ repair.location.label }}
            <p v-if="auth.role === 'contractor'" class="text-sm text-muted">{{ repair.building.address }}</p>
          </td>
          <td class="px-6 py-4 text-muted">{{ repair.category.name }}</td>
          <td class="px-6 py-4"><StatusChip :tone="statusTone[repair.status]">{{ repair.status_label }}</StatusChip></td>
          <td class="px-6 py-4 font-mono text-sm text-muted">{{ formatDateTime(repair.created_at) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
