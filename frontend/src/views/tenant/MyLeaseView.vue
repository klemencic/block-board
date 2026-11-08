<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import PageHeader from '@/components/PageHeader.vue'
import LeaseStatusChip from '@/components/LeaseStatusChip.vue'
import NoticeConfirmations from '@/components/NoticeConfirmations.vue'
import { leasesApi, type Lease } from '@/api/leases'
import { errorMessage } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { formatDate, formatMoney } from '@/utils/format'

const auth = useAuthStore()
const leases = ref<Lease[]>([])
const loading = ref(true)
const confirming = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)

onMounted(async () => {
  leases.value = await leasesApi.mine()
  loading.value = false
})

// Trenutni najem: aktiven ali v odpovednem roku, sicer zadnji.
const lease = computed(
  () => leases.value.find((l) => l.status === 'active' || l.status === 'in_notice') ?? leases.value[0] ?? null,
)
const me = computed(() => lease.value?.tenants?.find((t) => t.user_id === auth.user?.id) ?? null)
const currentTenants = computed(() => lease.value?.tenants?.filter((t) => !t.moved_out_on) ?? [])
const pending = computed(() => currentTenants.value.filter((t) => !t.notice_confirmed_at))

async function giveNotice() {
  busy.value = true
  error.value = null
  try {
    const updated = await leasesApi.giveNotice(lease.value!.id)
    leases.value = leases.value.map((l) => (l.id === updated.id ? { ...l, ...updated } : l))
    confirming.value = false
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <PageHeader title="Moj najem" :eyebrow="auth.membership?.building.name" />

  <div v-if="loading" class="text-muted">Nalagam …</div>
  <div v-else-if="!lease" class="card p-10 text-muted">Trenutno nimate najema v tej stavbi.</div>

  <div v-else class="grid gap-6 xl:grid-cols-[1fr_400px]">
    <div class="space-y-6">
      <section class="card p-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-2xl font-semibold">Stanovanje {{ lease.apartment?.label }}</h2>
          <LeaseStatusChip :status="lease.status" :label="lease.status_label" />
        </div>
        <p class="mt-1 text-muted">{{ lease.building?.name }} · {{ lease.building?.address }}</p>

        <dl class="mt-6 grid gap-x-8 gap-y-4 text-[15px] md:grid-cols-3">
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
            <dt class="text-muted">Najemnina stanovanja</dt>
            <dd class="font-mono">{{ formatMoney(lease.monthly_rent_cents) }}</dd>
          </div>
          <div>
            <dt class="text-muted">Vaš delež</dt>
            <dd class="font-mono font-semibold">{{ me ? formatMoney(me.rent_share_cents) : '—' }}</dd>
          </div>
          <div>
            <dt class="text-muted">Varščina</dt>
            <dd class="font-mono">{{ formatMoney(lease.deposit_cents) }}</dd>
          </div>
        </dl>
      </section>

      <section class="card p-8">
        <h2 class="text-2xl font-semibold">Sostanovalci</h2>
        <ul class="mt-4 divide-y divide-line">
          <li v-for="t in lease.tenants" :key="t.id" class="flex justify-between py-3 text-[15px]">
            <span>{{ t.name }}<span v-if="t.user_id === auth.user?.id" class="text-muted"> (vi)</span></span>
            <span class="text-muted">vselitev {{ formatDate(t.moved_in_on) }}</span>
          </li>
        </ul>
      </section>
    </div>

    <!-- Odpoved najema (US-02) -->
    <aside class="card-strong h-fit p-7">
      <template v-if="lease.status === 'active'">
        <p class="eyebrow">Odpoved najema</p>
        <h2 class="mt-1 text-2xl font-semibold">
          {{ me?.notice_confirmed_at ? 'Čakamo na sostanovalce' : 'Podajte odpoved' }}
        </h2>

        <div class="mt-5 rounded-lg bg-subtle p-4">
          <p class="text-sm text-muted">Če odpoved začne veljati danes, najem preneha</p>
          <p class="mt-1 font-display text-3xl font-semibold">{{ formatDate(lease.notice_preview_terminates_on) }}</p>
          <p class="mt-1 text-sm text-muted">
            Odpovedni rok {{ lease.notice_period_days }} dni<template v-if="lease.ends_on">, najpozneje do konca najema</template>.
          </p>
        </div>

        <template v-if="currentTenants.length > 1">
          <p class="mt-5 text-[15px]">
            Odpoved velja za celoten najem. Začne veljati, ko jo potrdijo vsi sostanovalci; datum odpovedi je dan
            zadnje potrditve.
          </p>
          <div class="mt-4"><NoticeConfirmations :tenants="currentTenants" /></div>
        </template>

        <p v-if="error" class="field-error">{{ error }}</p>

        <template v-if="!me?.notice_confirmed_at">
          <button v-if="!confirming" class="btn btn-danger mt-6 w-full" @click="confirming = true">
            Odpovej najem …
          </button>
          <div v-else class="mt-6 space-y-3">
            <p class="text-sm font-semibold">Ste prepričani? Potrditve ni mogoče preklicati.</p>
            <button class="btn btn-primary w-full" :disabled="busy" @click="giveNotice">Potrjujem odpoved</button>
            <button class="btn btn-secondary w-full" @click="confirming = false">Prekliči</button>
          </div>
        </template>
        <p v-else class="mt-5 text-sm text-muted">
          Odpoved ste potrdili. Še {{ pending.length }}
          {{ pending.length === 1 ? 'sostanovalec mora' : 'sostanovalci morajo' }} potrditi.
        </p>
      </template>

      <template v-else-if="lease.status === 'in_notice'">
        <p class="eyebrow">Odpovedni rok</p>
        <h2 class="mt-1 text-2xl font-semibold">Najem je odpovedan</h2>
        <dl class="mt-5 space-y-3 text-[15px]">
          <div class="flex justify-between">
            <dt class="text-muted">Datum odpovedi</dt>
            <dd class="font-mono">{{ formatDate(lease.notice_given_on) }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-muted">Datum prenehanja</dt>
            <dd class="font-mono font-semibold">{{ formatDate(lease.terminates_on) }}</dd>
          </div>
        </dl>
      </template>

      <template v-else>
        <p class="eyebrow">Stanje</p>
        <h2 class="mt-1 text-2xl font-semibold first-letter:uppercase">{{ lease.status_label }}</h2>
        <p v-if="lease.terminates_on" class="mt-3 text-muted">Najem je prenehal {{ formatDate(lease.terminates_on) }}.</p>
      </template>
    </aside>
  </div>
</template>
