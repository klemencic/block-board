<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import PageHeader from '@/components/PageHeader.vue'
import StatusChip from '@/components/StatusChip.vue'
import { announcementsApi, type Announcement } from '@/api/announcements'
import { leasesApi } from '@/api/leases'
import { validationErrors, type ValidationErrors } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { formatDateTime } from '@/utils/format'

// Novice in obvestila (US-14).
const auth = useAuthStore()
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
          </div>
          <p v-if="openId !== a.id" class="mt-2 line-clamp-2 text-muted">{{ a.body }}</p>
        </button>
        <div v-if="openId === a.id" class="mt-4 border-t border-line pt-4">
          <p class="text-[16px] leading-relaxed whitespace-pre-line">{{ a.body }}</p>
          <p class="mt-4 text-sm text-muted">Objavil {{ a.author }}</p>
        </div>
      </article>
    </section>

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
