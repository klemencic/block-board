<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import PageHeader from '@/components/PageHeader.vue'
import StatusChip from '@/components/StatusChip.vue'
import AppIcon from '@/components/AppIcon.vue'
import RepairActions from './RepairActions.vue'
import { eventTitle, repairsApi, STEPS, statusTone, type Repair } from '@/api/repairs'
import { formatDateTime, formatMoney } from '@/utils/format'

// Wireframe WF-03 »Obravnava prijave okvare« (US-09 do US-12).
const route = useRoute()
const repair = ref<Repair | null>(null)

async function load() {
  repair.value = await repairsApi.get(Number(route.params.id))
}
onMounted(load)
watch(() => route.params.id, load)

const stepIndex = computed(() => {
  if (!repair.value) return 0
  return Math.max(0, STEPS.findIndex((s) => s.status === repair.value!.status))
})

const waitingOnMe = computed(() => {
  const r = repair.value
  if (!r) return false
  return (r.permissions.manage && ['reported', 'confirmed', 'done'].includes(r.status)) ||
    (r.permissions.perform && ['assigned', 'scheduled'].includes(r.status))
})

const reportPhotos = computed(() => repair.value?.photos.filter((p) => p.context === 'report') ?? [])
const completionPhotos = computed(() => repair.value?.photos.filter((p) => p.context === 'completion') ?? [])

function shortTime(value: string): string {
  return formatDateTime(value).replace(/ \d{4} ·/, '')
}
</script>

<template>
  <template v-if="repair">
    <PageHeader :title="repair.title" :crumbs="[{ label: 'Okvare', to: '/okvare' }, { label: repair.number }]">
      <template #badge>
        <StatusChip :tone="statusTone[repair.status]" class="px-4 py-1 text-[16px] font-semibold">
          {{ repair.status_label }}<template v-if="waitingOnMe"> · čaka na vas</template>
        </StatusChip>
      </template>
      <template #meta>
        <p class="mt-2 flex flex-wrap gap-x-6 text-[17px] text-muted">
          <span>
            <template v-if="repair.location.apartment">
              Stanovanje <strong class="text-ink">{{ repair.location.apartment.label }}</strong> ·
              {{ repair.location.apartment.floor === 0 ? 'pritličje' : `${repair.location.apartment.floor}. nadstropje` }}
            </template>
            <template v-else>{{ repair.location.label }}</template>
          </span>
          <span>Prijavil {{ repair.reporter.name }}<template v-if="repair.reporter.phone"> · {{ repair.reporter.phone }}</template></span>
          <span class="font-mono">{{ formatDateTime(repair.created_at) }}</span>
        </p>
      </template>
    </PageHeader>

    <!-- Potek obravnave -->
    <section class="card mb-6 px-8 py-6">
      <p v-if="repair.status === 'rejected'" class="font-semibold text-danger">Prijava je bila zavrnjena.</p>
      <ol v-else class="grid grid-cols-6 gap-4">
        <li v-for="(step, i) in STEPS" :key="step.status">
          <div
            class="h-1 rounded-full"
            :class="i < stepIndex || repair.status === 'closed' ? 'bg-ink' : i === stepIndex + 1 ? 'bg-primary' : 'bg-subtle'"
          />
          <p class="mt-3 flex items-center gap-2 text-[16px]">
            <span
              class="grid size-7 shrink-0 place-items-center rounded-full text-sm"
              :class="
                i <= stepIndex
                  ? 'bg-ink text-white'
                  : i === stepIndex + 1
                    ? 'border-2 border-primary font-semibold text-primary'
                    : 'border border-line text-muted'
              "
            >
              <AppIcon v-if="i <= stepIndex" name="check" :size="14" />
              <template v-else>{{ i + 1 }}</template>
            </span>
            <span :class="i <= stepIndex ? 'font-semibold' : i === stepIndex + 1 ? 'font-semibold text-primary' : 'text-muted'">
              {{ step.label }}
            </span>
          </p>
        </li>
      </ol>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.75fr)]">
      <div class="space-y-6">
        <!-- Opis prijave -->
        <section class="card p-8">
          <div class="flex items-center justify-between gap-4">
            <h2 class="text-2xl font-semibold">Opis prijave</h2>
            <span class="chip">{{ repair.category.name }}</span>
          </div>
          <p class="mt-4 text-[17px] leading-relaxed whitespace-pre-line">{{ repair.description }}</p>
          <div v-if="reportPhotos.length" class="mt-6 grid grid-cols-3 gap-4">
            <a v-for="photo in reportPhotos" :key="photo.id" :href="photo.url" target="_blank" class="group block">
              <img :src="photo.url" :alt="photo.name" class="aspect-[4/3] w-full rounded-lg border border-line object-cover" />
              <p class="mt-1 truncate font-mono text-xs text-muted group-hover:underline">{{ photo.name }}</p>
            </a>
          </div>
          <dl class="mt-6 grid gap-3 border-t border-line pt-5 text-[15px] md:grid-cols-3">
            <div><dt class="text-muted">Plačnik</dt><dd class="font-semibold first-letter:uppercase">{{ repair.payer_label }}</dd></div>
            <div v-if="repair.contractor">
              <dt class="text-muted">Izvajalec</dt>
              <dd>{{ repair.contractor.name }}</dd>
            </div>
            <div v-if="repair.scheduled_at">
              <dt class="text-muted">Termin izvedbe</dt>
              <dd class="font-mono">{{ formatDateTime(repair.scheduled_at) }}</dd>
            </div>
          </dl>
        </section>

        <!-- Izvedba (poročilo izvajalca) -->
        <section v-if="repair.cost_cents !== null || completionPhotos.length" class="card p-8">
          <h2 class="text-2xl font-semibold">Poročilo izvajalca</h2>
          <p class="mt-3 text-[17px]">Strošek: <strong class="font-mono">{{ formatMoney(repair.cost_cents ?? 0) }}</strong></p>
          <div v-if="completionPhotos.length" class="mt-5 grid grid-cols-3 gap-4">
            <a v-for="photo in completionPhotos" :key="photo.id" :href="photo.url" target="_blank" class="block">
              <img :src="photo.url" :alt="photo.name" class="aspect-[4/3] w-full rounded-lg border border-line object-cover" />
              <p class="mt-1 truncate font-mono text-xs text-muted">{{ photo.name }}</p>
            </a>
          </div>
        </section>

        <!-- Časovnica -->
        <section class="card p-8">
          <h2 class="text-2xl font-semibold">Časovnica</h2>
          <ol class="mt-6">
            <li v-for="(event, i) in repair.timeline" :key="event.id" class="relative flex gap-4 pb-6 last:pb-0">
              <span
                v-if="i < (repair.timeline?.length ?? 0) - 1"
                class="absolute top-5 left-[7px] h-[calc(100%-12px)] w-px bg-line"
              />
              <span
                class="relative mt-1.5 size-[15px] shrink-0 rounded-full"
                :class="event.kind === 'status' ? 'bg-ink' : 'border-2 border-ink bg-surface'"
              />
              <div class="flex-1">
                <div class="flex justify-between gap-4">
                  <p class="text-[17px] font-semibold">{{ eventTitle(event) }}</p>
                  <p class="shrink-0 font-mono text-sm text-muted">{{ shortTime(event.created_at) }}</p>
                </div>
                <p class="text-muted">
                  {{ event.user }}<template v-if="event.note"> · {{ event.note }}</template>
                </p>
              </div>
            </li>
          </ol>
        </section>
      </div>

      <RepairActions :repair="repair" @updated="repair = $event" />
    </div>
  </template>
</template>
