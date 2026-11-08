<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import PageHeader from '@/components/PageHeader.vue'
import { repairsApi, type RepairForm } from '@/api/repairs'
import { errorMessage, validationErrors, type ValidationErrors } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { photoErrors } from '@/utils/photos'

const auth = useAuthStore()
const router = useRouter()
const options = ref<RepairForm | null>(null)
const errors = ref<ValidationErrors>({})
const general = ref<string | null>(null)
const busy = ref(false)
const photos = ref<File[]>([])

const form = reactive({ title: '', description: '', category_id: null as number | null, location: '' })

onMounted(async () => {
  options.value = await repairsApi.form(auth.buildingId!)
  if (options.value.apartments.length === 1) form.location = `a:${options.value.apartments[0].id}`
})

const category = computed(() => options.value?.categories.find((c) => c.id === form.category_id) ?? null)
const clientPhotoErrors = computed(() => photoErrors(photos.value, options.value?.max_photos, options.value?.max_photo_mb))

function onFiles(event: Event) {
  photos.value = [...photos.value, ...Array.from((event.target as HTMLInputElement).files ?? [])]
  ;(event.target as HTMLInputElement).value = ''
}

async function submit() {
  if (clientPhotoErrors.value.length) return
  busy.value = true
  errors.value = {}
  general.value = null
  const data = new FormData()
  data.append('title', form.title)
  data.append('description', form.description)
  if (form.category_id) data.append('category_id', String(form.category_id))
  const [kind, id] = form.location.split(':')
  if (kind === 'a') data.append('apartment_id', id)
  if (kind === 's') data.append('shared_space_id', id)
  photos.value.forEach((file) => data.append('photos[]', file))
  try {
    const repair = await repairsApi.create(auth.buildingId!, data)
    router.push(`/okvare/${repair.id}`)
  } catch (e) {
    errors.value = validationErrors(e)
    if (!Object.keys(errors.value).length) general.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}

const photoServerErrors = computed(() =>
  Object.entries(errors.value)
    .filter(([key]) => key.startsWith('photos'))
    .flatMap(([, messages]) => messages),
)
</script>

<template>
  <PageHeader title="Prijava okvare" :crumbs="[{ label: 'Okvare', to: '/okvare' }, { label: 'Nova prijava' }]" />

  <form v-if="options" class="grid gap-6 xl:grid-cols-[1fr_380px]" @submit.prevent="submit">
    <section class="card p-8">
      <label class="field-label" for="title">Naslov</label>
      <input id="title" v-model="form.title" class="field-input" placeholder="npr. Bojler ne greje vode" />
      <p v-if="errors.title" class="field-error">{{ errors.title[0] }}</p>

      <div class="mt-5 grid gap-5 md:grid-cols-2">
        <div>
          <label class="field-label" for="category">Vrsta okvare</label>
          <select id="category" v-model="form.category_id" class="field-input">
            <option :value="null" disabled>Izberite vrsto</option>
            <option v-for="c in options.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <p v-if="errors.category_id" class="field-error">{{ errors.category_id[0] }}</p>
          <p v-else-if="category" class="mt-2 rounded-lg bg-primary-soft px-3 py-2 text-sm text-primary">
            {{ category.name }} – krije {{ category.default_payer_label }}
          </p>
        </div>
        <div>
          <label class="field-label" for="location">Lokacija</label>
          <select id="location" v-model="form.location" class="field-input">
            <option value="" disabled>Izberite lokacijo</option>
            <optgroup v-if="options.apartments.length" label="Stanovanje">
              <option v-for="a in options.apartments" :key="a.id" :value="`a:${a.id}`">Stanovanje {{ a.label }}</option>
            </optgroup>
            <optgroup label="Skupni prostor">
              <option v-for="s in options.shared_spaces" :key="s.id" :value="`s:${s.id}`">{{ s.name }}</option>
            </optgroup>
          </select>
          <p v-if="errors.apartment_id || errors.shared_space_id" class="field-error">
            {{ (errors.apartment_id ?? errors.shared_space_id)[0] }}
          </p>
        </div>
      </div>

      <label class="field-label mt-5" for="description">Opis</label>
      <textarea id="description" v-model="form.description" rows="5" class="field-input" />
      <p v-if="errors.description" class="field-error">{{ errors.description[0] }}</p>

      <span class="field-label mt-5">Fotografije</span>
      <div class="flex flex-wrap gap-3">
        <div
          v-for="(photo, i) in photos"
          :key="i"
          class="flex items-center gap-2 rounded-lg border border-line bg-canvas px-3 py-2 font-mono text-sm"
        >
          {{ photo.name }}
          <button type="button" class="text-muted hover:text-danger" aria-label="Odstrani" @click="photos.splice(i, 1)">×</button>
        </div>
        <label class="btn btn-secondary cursor-pointer">
          + Dodaj fotografijo
          <input type="file" accept="image/jpeg,image/png" multiple class="hidden" @change="onFiles" />
        </label>
      </div>
      <p class="mt-2 text-sm text-muted">Do {{ options.max_photos }} fotografij v obliki JPG ali PNG, vsaka do {{ options.max_photo_mb }} MB.</p>
      <p v-for="message in [...clientPhotoErrors, ...photoServerErrors]" :key="message" class="field-error">{{ message }}</p>
    </section>

    <aside class="card-strong h-fit p-7">
      <p class="eyebrow">Oddaja</p>
      <h2 class="mt-1 text-2xl font-semibold">Prijavi okvaro</h2>
      <p class="mt-3 text-[15px] text-muted">
        Lastnik prejme e-pošto s povezavo na prijavo. Stanje in korake obravnave spremljate v časovnici.
      </p>
      <p v-if="general" class="field-error">{{ general }}</p>
      <button class="btn btn-primary mt-6 w-full" :disabled="busy || clientPhotoErrors.length > 0">Oddaj prijavo</button>
      <RouterLink to="/okvare" class="btn btn-secondary mt-3 w-full">Prekliči</RouterLink>
    </aside>
  </form>
</template>
