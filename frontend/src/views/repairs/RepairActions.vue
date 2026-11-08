<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { http, errorMessage, validationErrors } from '@/api/http'
import { repairsApi, type Payer, type Repair } from '@/api/repairs'
import { useAuthStore } from '@/stores/auth'

// Plošča »Vaš korak« po wireframu WF-03 (US-10). Koraki izvajalca in zaključek sledijo v US-11 in US-12.
const props = defineProps<{ repair: Repair }>()
const emit = defineEmits<{ updated: [repair: Repair] }>()

const auth = useAuthStore()
const contractors = ref<{ id: number; name: string }[]>([])
const payer = ref<Payer>(props.repair.payer)
const contractorId = ref<number | null>(null)
const rejecting = ref(false)
const reason = ref('')
const error = ref<string | null>(null)
const busy = ref(false)

watch(() => props.repair, (r) => (payer.value = r.payer))

const isLandlord = computed(() => props.repair.permissions.manage)
const canDecide = computed(() => isLandlord.value && ['reported', 'confirmed'].includes(props.repair.status))
const canChangePayer = computed(
  () => isLandlord.value && !['reported', 'confirmed', 'closed', 'rejected'].includes(props.repair.status),
)

watch(
  canDecide,
  async (show) => {
    if (show && contractors.value.length === 0) {
      contractors.value = (await http.get(`/api/buildings/${auth.buildingId}/contractors`)).data.data
      contractorId.value = contractors.value[0]?.id ?? null
    }
  },
  { immediate: true },
)

async function run(action: string, payload: Record<string, unknown> = {}) {
  busy.value = true
  error.value = null
  try {
    emit('updated', await repairsApi.action(props.repair.id, action, payload))
    rejecting.value = false
    reason.value = ''
  } catch (e) {
    const errors = validationErrors(e)
    error.value = Object.values(errors)[0]?.[0] ?? errorMessage(e)
  } finally {
    busy.value = false
  }
}

function confirmAndAssign() {
  if (props.repair.status === 'reported') {
    run('confirm', { payer: payer.value, contractor_id: contractorId.value })
  } else {
    if (payer.value !== props.repair.payer) run('payer', { payer: payer.value })
    run('assign', { contractor_id: contractorId.value })
  }
}

const payerOptions: { value: Payer; label: string }[] = [
  { value: 'landlord', label: 'Lastnik' },
  { value: 'tenant', label: 'Najemnik' },
]
</script>

<template>
  <aside class="h-fit space-y-6">
    <!-- Lastnik: potrditev, plačnik in izvajalec (US-10) -->
    <section v-if="canDecide" class="card-strong p-7">
      <p class="eyebrow">Vaš korak</p>
      <h2 class="mt-1 text-[28px] font-semibold">{{ repair.status === 'reported' ? 'Potrdi in dodeli' : 'Dodeli izvajalca' }}</h2>

      <p class="field-label mt-6 text-[16px]">Plačnik popravila</p>
      <label
        v-for="option in payerOptions"
        :key="option.value"
        class="mt-2 flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3.5 text-[17px]"
        :class="payer === option.value ? 'border-2 border-primary bg-primary-soft' : 'border-line'"
      >
        <input v-model="payer" type="radio" :value="option.value" class="size-5 accent-primary" />
        <span class="flex-1">{{ option.label }}</span>
        <span v-if="repair.default_payer === option.value" class="text-sm font-semibold text-primary">privzeto</span>
      </label>
      <p class="mt-2 text-sm text-muted">Sprememba se zabeleži v časovnici.</p>

      <label class="field-label mt-6 text-[16px]" for="contractor">Izvajalec</label>
      <select id="contractor" v-model="contractorId" class="field-input py-3">
        <option v-for="c in contractors" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <p class="mt-2 text-sm text-muted">Samo izvajalci, povabljeni v stavbo.</p>

      <p v-if="error" class="mt-4 rounded-lg bg-danger-soft p-3 text-danger">{{ error }}</p>

      <button class="btn btn-primary mt-6 w-full py-3.5 text-lg" :disabled="busy || !contractorId" @click="confirmAndAssign">
        {{ repair.status === 'reported' ? 'Potrdi in dodeli izvajalcu' : 'Dodeli izvajalcu' }}
      </button>

      <template v-if="repair.status === 'reported'">
        <div v-if="rejecting" class="mt-4">
          <label class="field-label" for="reason">Obrazložitev zavrnitve</label>
          <textarea id="reason" v-model="reason" rows="3" class="field-input" />
          <div class="mt-3 flex gap-3">
            <button class="btn btn-secondary flex-1" @click="rejecting = false">Prekliči</button>
            <button class="btn btn-danger flex-1" :disabled="busy" @click="run('reject', { reason })">Zavrni prijavo</button>
          </div>
        </div>
        <template v-else>
          <button class="btn btn-danger mt-3 w-full py-3 text-lg" @click="rejecting = true">Zavrni prijavo…</button>
          <p class="mt-3 text-center text-sm text-muted">Pri zavrnitvi je obrazložitev obvezna.</p>
        </template>
      </template>
    </section>

    <!-- Lastnik: sprememba plačnika med izvedbo (US-10, #122) -->
    <section v-if="canChangePayer" class="card p-7">
      <p class="eyebrow">Plačnik popravila</p>
      <div class="mt-3 flex gap-2">
        <button
          v-for="option in payerOptions"
          :key="option.value"
          class="btn flex-1"
          :class="repair.payer === option.value ? 'btn-primary' : 'btn-secondary'"
          :disabled="busy || repair.payer === option.value"
          @click="run('payer', { payer: option.value })"
        >
          {{ option.label }}
        </button>
      </div>
      <p class="mt-2 text-sm text-muted">Sprememba se zabeleži v časovnici.</p>
    </section>

    <section v-if="!canDecide" class="card p-7 text-[15px] text-muted">
      <p class="eyebrow">Stanje</p>
      <p class="mt-2">Prijava je v stanju »{{ repair.status_label }}«. O vsaki spremembi boste obveščeni po e-pošti.</p>
    </section>
  </aside>
</template>
