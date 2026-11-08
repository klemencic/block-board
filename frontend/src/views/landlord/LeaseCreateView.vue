<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/PageHeader.vue'
import MoneyInput from '@/components/MoneyInput.vue'
import { floorLabel, leasesApi, type Apartment } from '@/api/leases'
import { errorMessage, validationErrors, type ValidationErrors } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { formatMoney } from '@/utils/format'
import { shareDifferenceMessage } from '@/utils/shares'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const apartments = ref<Apartment[]>([])
const apartmentId = ref<number | null>(route.query.apartment ? Number(route.query.apartment) : null)
const errors = ref<ValidationErrors>({})
const generalError = ref<string | null>(null)
const busy = ref(false)

const form = reactive({
  starts_on: '',
  ends_on: '',
  notice_period_days: 30 as number | null,
  monthly_rent_cents: null as number | null,
  deposit_cents: null as number | null,
  tenants: [{ name: '', email: '', phone: '', rent_share_cents: null as number | null }],
})

onMounted(async () => {
  apartments.value = await leasesApi.apartments(auth.buildingId!)
})

const shareMessage = computed(() => {
  if (form.monthly_rent_cents === null) return null
  return shareDifferenceMessage(
    form.monthly_rent_cents,
    form.tenants.map((t) => t.rent_share_cents ?? 0),
  )
})

const shareSum = computed(() => form.tenants.reduce((s, t) => s + (t.rent_share_cents ?? 0), 0))

function addTenant() {
  form.tenants.push({ name: '', email: '', phone: '', rent_share_cents: null })
}

function splitEvenly() {
  if (!form.monthly_rent_cents) return
  const n = form.tenants.length
  const base = Math.floor(form.monthly_rent_cents / n)
  const remainder = form.monthly_rent_cents - base * n
  form.tenants.forEach((t, i) => (t.rent_share_cents = base + (i < remainder ? 1 : 0)))
}

function err(field: string): string | undefined {
  return errors.value[field]?.[0]
}

async function submit() {
  if (!apartmentId.value) {
    errors.value = { apartment: ['Izberite stanovanje.'] }
    return
  }
  busy.value = true
  errors.value = {}
  generalError.value = null
  try {
    const lease = await leasesApi.create(apartmentId.value, {
      starts_on: form.starts_on,
      ends_on: form.ends_on || null,
      notice_period_days: form.notice_period_days ?? 0,
      monthly_rent_cents: form.monthly_rent_cents ?? 0,
      deposit_cents: form.deposit_cents ?? 0,
      tenants: form.tenants.map((t) => ({
        name: t.name,
        email: t.email,
        phone: t.phone || null,
        rent_share_cents: t.rent_share_cents ?? 0,
      })),
    })
    router.push(`/najemi/${lease.id}`)
  } catch (e) {
    errors.value = validationErrors(e)
    if (Object.keys(errors.value).length === 0) generalError.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <PageHeader title="Nov najem" :crumbs="[{ label: 'Najemi', to: '/najemi' }, { label: 'Nov najem' }]" />

  <form class="grid gap-6 xl:grid-cols-[1fr_380px]" @submit.prevent="submit">
    <div class="space-y-6">
      <section class="card p-8">
        <h2 class="text-2xl font-semibold">Pogoji najema</h2>

        <div class="mt-6 grid gap-5 md:grid-cols-2">
          <div class="md:col-span-2">
            <label class="field-label" for="apartment">Stanovanje</label>
            <select id="apartment" v-model="apartmentId" class="field-input">
              <option :value="null" disabled>Izberite stanovanje</option>
              <option v-for="a in apartments" :key="a.id" :value="a.id">
                {{ a.label }} · {{ floorLabel(a.floor) === 'P' ? 'pritličje' : `${a.floor}. nad.` }} ·
                {{ a.area_m2 }} m²{{ a.occupancy !== 'vacant' ? ' · zasedeno' : '' }}
              </option>
            </select>
            <p v-if="err('apartment')" class="field-error">{{ err('apartment') }}</p>
          </div>

          <div>
            <label class="field-label" for="starts_on">Datum začetka</label>
            <input id="starts_on" v-model="form.starts_on" type="date" class="field-input font-mono" />
            <p v-if="err('starts_on')" class="field-error">{{ err('starts_on') }}</p>
          </div>
          <div>
            <label class="field-label" for="ends_on">Datum konca <span class="font-normal text-muted">(neobvezno)</span></label>
            <input id="ends_on" v-model="form.ends_on" type="date" class="field-input font-mono" />
            <p v-if="err('ends_on')" class="field-error">{{ err('ends_on') }}</p>
            <p v-else class="mt-1 text-sm text-muted">Brez datuma konca je najem sklenjen za nedoločen čas.</p>
          </div>

          <div>
            <label class="field-label" for="rent">Mesečna najemnina</label>
            <MoneyInput id="rent" v-model="form.monthly_rent_cents" />
            <p v-if="err('monthly_rent_cents')" class="field-error">{{ err('monthly_rent_cents') }}</p>
          </div>
          <div>
            <label class="field-label" for="deposit">Varščina</label>
            <MoneyInput id="deposit" v-model="form.deposit_cents" />
            <p v-if="err('deposit_cents')" class="field-error">{{ err('deposit_cents') }}</p>
          </div>
          <div>
            <label class="field-label" for="notice">Odpovedni rok (dni)</label>
            <input
              id="notice"
              v-model.number="form.notice_period_days"
              type="number"
              min="0"
              class="field-input font-mono"
            />
            <p v-if="err('notice_period_days')" class="field-error">{{ err('notice_period_days') }}</p>
          </div>
        </div>
      </section>

      <section class="card p-8">
        <div class="flex items-center justify-between">
          <h2 class="text-2xl font-semibold">Najemniki</h2>
          <button type="button" class="btn btn-secondary" @click="splitEvenly">Razdeli enakomerno</button>
        </div>

        <div
          v-for="(tenant, i) in form.tenants"
          :key="i"
          class="mt-5 grid gap-4 border-t border-line pt-5 md:grid-cols-[1fr_1fr_160px_160px_auto]"
        >
          <div>
            <label class="field-label">Ime in priimek</label>
            <input v-model="tenant.name" class="field-input" />
            <p v-if="err(`tenants.${i}.name`)" class="field-error">{{ err(`tenants.${i}.name`) }}</p>
          </div>
          <div>
            <label class="field-label">E-naslov</label>
            <input v-model="tenant.email" type="email" class="field-input" />
            <p v-if="err(`tenants.${i}.email`)" class="field-error">{{ err(`tenants.${i}.email`) }}</p>
          </div>
          <div>
            <label class="field-label">Telefon</label>
            <input v-model="tenant.phone" class="field-input" />
          </div>
          <div>
            <label class="field-label">Delež najemnine</label>
            <MoneyInput v-model="tenant.rent_share_cents" />
            <p v-if="err(`tenants.${i}.rent_share_cents`)" class="field-error">
              {{ err(`tenants.${i}.rent_share_cents`) }}
            </p>
          </div>
          <div class="flex items-end">
            <button
              v-if="form.tenants.length > 1"
              type="button"
              class="btn btn-danger"
              aria-label="Odstrani najemnika"
              @click="form.tenants.splice(i, 1)"
            >
              ✕
            </button>
          </div>
        </div>

        <button type="button" class="btn btn-secondary mt-5" @click="addTenant">+ Dodaj sostanovalca</button>

        <div class="mt-5 flex items-center justify-between rounded-lg bg-subtle px-4 py-3 text-sm">
          <span>Vsota deležev</span>
          <span class="font-mono font-semibold">
            {{ formatMoney(shareSum) }} / {{ formatMoney(form.monthly_rent_cents ?? 0) }}
          </span>
        </div>
        <p v-if="err('tenants')" class="field-error">{{ err('tenants') }}</p>
        <p v-else-if="shareMessage" class="field-error">{{ shareMessage }}</p>
      </section>
    </div>

    <aside class="card-strong h-fit p-7">
      <p class="eyebrow">Shranjevanje</p>
      <h2 class="mt-1 text-2xl font-semibold">Najem v stanju osnutek</h2>
      <p class="mt-3 text-[15px] text-muted">
        Osnutek še ne ustvarja obveznosti. Najemniki brez računa prejmejo e-poštno povabilo s povezavo za
        nastavitev gesla, ki velja 7 dni. Najem nato aktivirate na strani najema.
      </p>
      <p v-if="generalError" class="field-error">{{ generalError }}</p>
      <button class="btn btn-primary mt-6 w-full" :disabled="busy">Shrani osnutek</button>
      <RouterLink to="/najemi" class="btn btn-secondary mt-3 w-full">Prekliči</RouterLink>
    </aside>
  </form>
</template>
