<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import PageHeader from '@/components/PageHeader.vue'
import StatusChip from '@/components/StatusChip.vue'
import BaseModal from '@/components/BaseModal.vue'
import { announcementsApi, type Announcement, type UninformedApartment } from '@/api/announcements'
import { useBadgesStore } from '@/stores/badges'
import { leasesApi } from '@/api/leases'
import { validationErrors, type ValidationErrors } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { formatDateTime } from '@/utils/format'

// Novice in obvestila (US-14).
const auth = useAuthStore()
const badges = useBadgesStore()
const route = useRoute()
const isLandlord = computed(() => auth.role === 'landlord')

const announcements = ref<Announcement[]>([])
const loading = ref(true)
const openId = ref<number | null>(route.params.id ? Number(route.params.id) : null)

const floors = ref<number[]>([])
const form = reactive({ title: '', body: '', target: 'building' as 'building' | number })
const errors = ref<ValidationErrors>({})
const busy = ref(false)
const published = ref<string | null>(null)

async function load() {
  announcements.value = await announcementsApi.list(auth.buildingId!)
  loading.value = false
}

onMounted(async () => {
  await load()
  if (isLandlord.value) {
    const apartments = await leasesApi.apartments(auth.buildingId!)
    floors.value = [...new Set(apartments.map((a) => a.floor))].sort((a, b) => a - b)
  }
})

async function publish() {
  busy.value = true
  errors.value = {}
  published.value = null
  try {
    const result = await announcementsApi.publish(auth.buildingId!, {
      title: form.title,
      body: form.body,
      floor: form.target === 'building' ? null : form.target,
    })
    published.value = `Obvestilo je objavljeno. E-pošta je poslana ${result.delivery?.recipients ?? 0} najemnikom.`
    Object.assign(form, { title: '', body: '', target: 'building' })
    await load()
  } catch (e) {
    errors.value = validationErrors(e)
  } finally {
    busy.value = false
  }
}

const floorLabel = (floor: number) => (floor === 0 ? 'Pritličje' : `${floor}. nadstropje`)

// Potrditev branja (US-15)
async function confirmRead(a: Announcement) {
  busy.value = true
  try {
    const updated = await announcementsApi.confirmRead(a.id)
    announcements.value = announcements.value.map((x) => (x.id === a.id ? { ...x, read_at: updated.read_at } : x))
    badges.set('/obvestila', announcements.value.filter((x) => !x.read_at).length)
  } finally {
    busy.value = false
  }
}

const uninformedFor = ref<Announcement | null>(null)
const uninformed = ref<UninformedApartment[] | null>(null)

async function showUninformed(a: Announcement) {
  uninformedFor.value = a
  uninformed.value = null
  uninformed.value = await announcementsApi.uninformed(a.id)
}
</script>

<template>
  <PageHeader title="Obvestila" :eyebrow="auth.membership?.building.name" />

  <div class="grid gap-6" :class="isLandlord ? 'xl:grid-cols-[minmax(0,1fr)_420px]' : ''">
    <section class="space-y-4">
      <p v-if="loading" class="text-muted">Nalagam …</p>
      <div v-else-if="announcements.length === 0" class="card p-10 text-muted">Ni objavljenih obvestil.</div>

      <article
        v-for="a in announcements"
        :key="a.id"
        class="card p-6"
        :class="openId === a.id ? 'border-ink/40' : ''"
      >
        <button class="block w-full text-left" @click="openId = openId === a.id ? null : a.id">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="font-mono text-sm text-muted">{{ formatDateTime(a.published_at) }} · {{ a.target_label }}</p>
              <h2 class="mt-1 text-xl font-semibold">{{ a.title }}</h2>
            </div>
            <div v-if="a.delivery" class="text-right text-sm text-muted">
              E-pošta: {{ a.delivery.sent }} / {{ a.delivery.recipients }}
              <StatusChip v-if="a.delivery.failed" tone="danger" class="ml-2">{{ a.delivery.failed }} neuspešno</StatusChip>
            </div>
            <StatusChip v-else-if="a.read_at" tone="success">Prebrano</StatusChip>
            <StatusChip v-else tone="primary">Novo</StatusChip>
          </div>
          <p v-if="openId !== a.id" class="mt-2 line-clamp-2 text-muted">{{ a.body }}</p>
        </button>
        <div v-if="openId === a.id" class="mt-4 border-t border-line pt-4">
          <p class="text-[16px] leading-relaxed whitespace-pre-line">{{ a.body }}</p>
          <p class="mt-4 text-sm text-muted">Objavil {{ a.author }}</p>
        </div>

        <!-- Najemnik: potrditev branja (US-15, #136) -->
        <div v-if="!isLandlord" class="mt-4 flex items-center justify-between gap-4 border-t border-line pt-4">
          <p v-if="a.read_at" class="text-sm text-muted">Branje ste potrdili {{ formatDateTime(a.read_at) }}.</p>
          <button v-else class="btn btn-primary" :disabled="busy" @click="confirmRead(a)">Potrjujem, da sem prebral</button>
        </div>

        <!-- Lastnik: obveščenost stanovanj (US-15, #137) -->
        <div v-if="a.informed" class="mt-4 flex items-center justify-between gap-4 border-t border-line pt-4">
          <span class="text-sm text-muted">Obveščenost stanovanj</span>
          <button
            class="rounded-full px-4 py-1 text-[15px] font-semibold"
            :class="a.informed.informed === a.informed.total ? 'bg-[#e7f4ec] text-success' : 'bg-primary-soft text-primary hover:underline'"
            @click="showUninformed(a)"
          >
            {{ a.informed.informed }} od {{ a.informed.total }} stanovanj
          </button>
        </div>
      </article>
    </section>

    <BaseModal
      v-if="uninformedFor"
      eyebrow="Branja še niso potrdila"
      :title="uninformedFor.title"
      @close="uninformedFor = null"
    >
      <p v-if="uninformed === null" class="text-muted">Nalagam …</p>
      <p v-else-if="uninformed.length === 0" class="text-success">Vsa stanovanja so obveščena.</p>
      <ul v-else class="divide-y divide-line">
        <li v-for="apt in uninformed" :key="apt.id" class="py-3">
          <p class="font-semibold">Stanovanje {{ apt.label }} <span class="font-normal text-muted">· {{ floorLabel(apt.floor) }}</span></p>
          <p v-for="t in apt.tenants" :key="t.name" class="flex justify-between text-[15px]">
            <span>{{ t.name }}</span>
            <a v-if="t.phone" :href="`tel:${t.phone}`" class="font-mono text-primary">{{ t.phone }}</a>
          </p>
        </li>
      </ul>
    </BaseModal>

    <!-- Lastnik: nova objava (US-14, #133) -->
    <aside v-if="isLandlord" class="card-strong h-fit p-7">
      <p class="eyebrow">Nova objava</p>
      <h2 class="mt-1 text-2xl font-semibold">Objavi obvestilo</h2>

      <label class="field-label mt-5" for="title">Naslov</label>
      <input id="title" v-model="form.title" class="field-input" placeholder="npr. Zapora vode v četrtek" />
      <p v-if="errors.title" class="field-error">{{ errors.title[0] }}</p>

      <label class="field-label mt-4" for="body">Besedilo</label>
      <textarea id="body" v-model="form.body" rows="6" class="field-input" />
      <p v-if="errors.body" class="field-error">{{ errors.body[0] }}</p>

      <label class="field-label mt-4" for="target">Prejemniki</label>
      <select id="target" v-model="form.target" class="field-input">
        <option value="building">Cela stavba</option>
        <option v-for="f in floors" :key="f" :value="f">{{ floorLabel(f) }}</option>
      </select>
      <p v-if="errors.floor" class="field-error">{{ errors.floor[0] }}</p>
      <p class="mt-2 text-sm text-muted">
        Obvestilo za nadstropje vidijo samo najemniki stanovanj v tem nadstropju. Vsi prejemniki dobijo e-pošto s povezavo.
      </p>

      <p v-if="published" class="mt-4 rounded-lg bg-[#e7f4ec] p-3 text-success">{{ published }}</p>
      <button class="btn btn-primary mt-6 w-full py-3" :disabled="busy" @click="publish">Objavi</button>
    </aside>
  </div>
</template>
