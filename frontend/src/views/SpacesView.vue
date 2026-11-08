<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import PageHeader from '@/components/PageHeader.vue'
import AppIcon from '@/components/AppIcon.vue'
import { spacesApi, type Availability, type CalendarBooking, type SharedSpace } from '@/api/spaces'
import { errorMessage, validationErrors } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { formatMoney, monthName } from '@/utils/format'
import {
  addDays,
  DAY_NAMES,
  dayLabel,
  fromMinutes,
  hoursLabel,
  isoDate,
  mondayOf,
  parseDate,
  toMinutes,
  weekLabel,
} from '@/utils/calendar'

// Wireframe WF-02 »Rezerviraj skupni prostor« (US-08).
const SLOT = 30
const SLOT_HEIGHT = 26

const auth = useAuthStore()
const spaces = ref<SharedSpace[]>([])
const spaceId = ref<number | null>(null)
const week = ref(mondayOf(isoDate(new Date())))
const data = ref<Availability | null>(null)
const now = ref(new Date())
const selection = ref<{ date: string; start: number; end: number } | null>(null)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const busy = ref(false)

const timer = setInterval(() => (now.value = new Date()), 60_000)
onUnmounted(() => clearInterval(timer))

onMounted(async () => {
  spaces.value = await spacesApi.list(auth.buildingId!)
  spaceId.value = spaces.value[0]?.id ?? null
})

async function load() {
  if (!spaceId.value) return
  data.value = await spacesApi.availability(spaceId.value, week.value)
}
watch([spaceId, week], () => {
  selection.value = null
  error.value = null
  load()
})

const space = computed(() => data.value?.space ?? null)
const days = computed(() => Array.from({ length: 7 }, (_, i) => addDays(week.value, i)))
const openFrom = computed(() => (space.value ? toMinutes(space.value.open_from) : 480))
const openTo = computed(() => (space.value ? toMinutes(space.value.open_to) : 1320))
const slots = computed(() => {
  const list: number[] = []
  for (let m = openFrom.value; m < openTo.value; m += SLOT) list.push(m)
  return list
})
const hours = computed(() => {
  const list: number[] = []
  for (let m = Math.ceil(openFrom.value / 60) * 60; m <= openTo.value; m += 60) list.push(m)
  return list
})
const today = computed(() => isoDate(now.value))
const nowMinutes = computed(() => now.value.getHours() * 60 + now.value.getMinutes())

function bookingsOn(date: string): (CalendarBooking & { start: number; end: number })[] {
  return (data.value?.bookings ?? [])
    .filter((b) => b.starts_at.startsWith(date))
    .map((b) => ({ ...b, start: toMinutes(b.starts_at.slice(11, 16)), end: toMinutes(b.ends_at.slice(11, 16)) }))
}

function isPast(date: string, minute: number): boolean {
  return date < today.value || (date === today.value && minute < nowMinutes.value)
}

function isTaken(date: string, start: number, end: number): boolean {
  return bookingsOn(date).some((b) => b.start < end && b.end > start)
}

// Prvi klik izbere začetek (30 min), naslednji klik na isti dan podaljša termin do izbrane polure.
function pick(date: string, minute: number) {
  success.value = null
  error.value = null
  if (!data.value?.can_book || isPast(date, minute) || isTaken(date, minute, minute + SLOT)) return
  const sel = selection.value
  if (sel && sel.date === date && minute >= sel.start && !(minute === sel.start && sel.end === sel.start + SLOT)) {
    if (!isTaken(date, sel.start, minute + SLOT)) {
      selection.value = { ...sel, end: minute + SLOT }
      return
    }
  }
  selection.value = sel && sel.date === date && sel.start === minute && sel.end === minute + SLOT ? null : { date, start: minute, end: minute + SLOT }
}

const selectedMinutes = computed(() => (selection.value ? selection.value.end - selection.value.start : 0))
const fee = computed(() =>
  space.value ? Math.round((space.value.price_per_hour_cents * selectedMinutes.value) / 60) : 0,
)
const chargeMonth = computed(() => {
  if (!selection.value) return ''
  const d = parseDate(selection.value.date)
  return monthName(((d.getMonth() + 1) % 12) + 1)
})
const freeCancelUntil = computed(() => {
  if (!selection.value || !space.value) return null
  const start = parseDate(selection.value.date)
  start.setMinutes(selection.value.start - space.value.free_cancel_hours * 60)
  return `${dayLabel(isoDate(start), false).slice(0, -5)} ob ${String(start.getHours()).padStart(2, '0')}.${String(start.getMinutes()).padStart(2, '0')}`
})

// Trak tedenske omejitve: porabljene ure, izbrane ure, preostanek.
const limitSegments = computed(() => {
  const usage = data.value?.usage
  if (!usage) return []
  const total = usage.max_minutes / 60
  const used = usage.used_minutes / 60
  const selected = selectedMinutes.value / 60
  return Array.from({ length: total }, (_, i) => (i < used ? 'used' : i < used + selected ? 'selected' : 'free'))
})
const overLimit = computed(() => !!data.value?.usage && selectedMinutes.value > data.value.usage.available_minutes)

async function submit() {
  if (!selection.value || !spaceId.value) return
  busy.value = true
  error.value = null
  const { date, start, end } = selection.value
  try {
    const result = await spacesApi.book(spaceId.value, `${date} ${fromMinutes(start)}`, `${date} ${fromMinutes(end)}`)
    success.value =
      result.fee_cents > 0
        ? `Termin je rezerviran. ${formatMoney(result.fee_cents)} bo dodano k obračunu za ${result.charge_period_label}.`
        : 'Termin je rezerviran.'
    selection.value = null
    await load()
  } catch (e) {
    const errors = validationErrors(e)
    error.value = errors.ends_at?.[0] ?? errors.starts_at?.[0] ?? errorMessage(e)
    await load()
  } finally {
    busy.value = false
  }
}

const top = (minute: number) => ((minute - openFrom.value) / SLOT) * SLOT_HEIGHT
</script>

<template>
  <PageHeader
    :title="auth.role === 'tenant' ? 'Rezerviraj skupni prostor' : 'Skupni prostori'"
    :eyebrow="data ? weekLabel(data.week_start, data.week_end) : ''"
  >
    <template #actions>
      <button class="btn btn-secondary px-3" aria-label="Prejšnji teden" @click="week = addDays(week, -7)">
        <AppIcon name="chevronLeft" />
      </button>
      <button class="btn btn-secondary" @click="week = mondayOf(isoDate(new Date()))">Ta teden</button>
      <button class="btn btn-secondary px-3" aria-label="Naslednji teden" @click="week = addDays(week, 7)">
        <AppIcon name="chevronRight" />
      </button>
    </template>
  </PageHeader>

  <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div class="flex gap-1 rounded-xl bg-subtle p-1.5">
      <button
        v-for="s in spaces"
        :key="s.id"
        class="rounded-lg px-6 py-2.5 text-[16px]"
        :class="spaceId === s.id ? 'bg-surface font-semibold shadow-sm' : 'text-muted hover:text-ink'"
        @click="spaceId = s.id"
      >
        {{ s.name }}
      </button>
    </div>
    <div class="flex flex-wrap gap-6 text-[15px]">
      <span class="flex items-center gap-2">
        <span class="size-4 rounded-sm border border-line bg-[repeating-linear-gradient(135deg,#e7e5df_0_4px,#f4f3ef_4px_8px)]" /> Zasedeno
      </span>
      <span class="flex items-center gap-2"><span class="size-4 rounded-sm bg-ink" /> Rezervacija stanovanja</span>
      <span v-if="data?.can_book" class="flex items-center gap-2"><span class="size-4 rounded-sm bg-primary" /> Izbran termin</span>
    </div>
  </div>

  <div v-if="space" class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_400px]">
    <!-- Tedenski koledar -->
    <div class="card overflow-x-auto p-5">
      <div class="grid min-w-[760px] grid-cols-[56px_repeat(7,minmax(0,1fr))]">
        <div />
        <div v-for="(day, i) in days" :key="day" class="pb-3 pl-3" :class="day === today ? 'text-primary' : 'text-muted'">
          <p class="text-sm" :class="day === today ? 'font-semibold' : ''">{{ DAY_NAMES[i] }}<template v-if="day === today"> · danes</template></p>
          <p class="text-xl" :class="day === today ? 'font-semibold text-ink' : ''">{{ parseDate(day).getDate() }}</p>
        </div>

        <!-- Ure -->
        <div class="relative border-t border-line" :style="{ height: `${slots.length * SLOT_HEIGHT}px` }">
          <span
            v-for="h in hours"
            :key="h"
            class="absolute -translate-y-1/2 font-mono text-xs text-muted"
            :style="{ top: `${top(h)}px` }"
          >{{ fromMinutes(h) }}</span>
        </div>

        <!-- Dnevi -->
        <div
          v-for="day in days"
          :key="day"
          class="relative border-t border-l border-line"
          :class="day < today ? 'bg-canvas' : ''"
          :style="{ height: `${slots.length * SLOT_HEIGHT}px` }"
        >
          <button
            v-for="m in slots"
            :key="m"
            type="button"
            class="block w-full border-b border-line/60"
            :class="[
              m % 60 === 30 ? 'border-dashed' : '',
              data?.can_book && !isPast(day, m) && !isTaken(day, m, m + SLOT) ? 'cursor-pointer hover:bg-primary-soft' : 'cursor-default',
            ]"
            :style="{ height: `${SLOT_HEIGHT}px` }"
            :aria-label="`${day} ${fromMinutes(m)}`"
            @click="pick(day, m)"
          />

          <div
            v-for="b in bookingsOn(day)"
            :key="b.id"
            class="pointer-events-none absolute inset-x-1 overflow-hidden rounded-md px-2 py-1 text-sm"
            :class="
              b.own
                ? 'bg-ink/80 text-white'
                : 'border border-line bg-[repeating-linear-gradient(135deg,#e7e5df_0_6px,#f1efea_6px_12px)] text-ink'
            "
            :style="{ top: `${top(b.start) + 2}px`, height: `${top(b.end) - top(b.start) - 4}px` }"
          >
            <template v-if="b.own">
              <p class="font-semibold">Vaše · {{ b.apartment }}</p>
              <p class="font-mono text-xs opacity-80">{{ fromMinutes(b.start) }}–{{ fromMinutes(b.end) }}</p>
            </template>
            <template v-else>
              <p>{{ b.apartment ? `Zasedeno · ${b.apartment}` : 'Zasedeno' }}</p>
            </template>
          </div>

          <div
            v-if="selection && selection.date === day"
            class="pointer-events-none absolute inset-x-1 rounded-md bg-primary px-2 py-1 text-sm text-white ring-4 ring-primary/20"
            :style="{ top: `${top(selection.start) + 2}px`, height: `${top(selection.end) - top(selection.start) - 4}px` }"
          >
            <p class="font-semibold">Izbrano</p>
            <p class="font-mono text-xs">{{ fromMinutes(selection.start) }}–{{ fromMinutes(selection.end) }}</p>
            <p class="font-mono text-xs">{{ hoursLabel(selectedMinutes) }}</p>
          </div>

          <div
            v-if="day === today && nowMinutes >= openFrom && nowMinutes <= openTo"
            class="pointer-events-none absolute inset-x-0 h-0.5 bg-danger"
            :style="{ top: `${top(nowMinutes)}px` }"
          >
            <span class="absolute -top-1 -left-1 size-2.5 rounded-full bg-danger" />
          </div>
        </div>
      </div>
      <p v-if="data?.can_book" class="mt-4 text-sm text-muted">
        Kliknite začetek termina, nato še zadnjo polure. Obratovalni čas {{ space.open_from }}–{{ space.open_to }}.
      </p>
    </div>

    <!-- Nova rezervacija -->
    <aside v-if="data?.can_book" class="card-strong h-fit p-7">
      <p class="eyebrow">Nova rezervacija</p>
      <h2 class="mt-1 text-[28px] font-semibold">{{ space.name }}</h2>

      <template v-if="selection">
        <dl class="mt-5 space-y-3 text-[16px]">
          <div class="flex justify-between"><dt class="text-muted">Datum</dt><dd class="font-medium">{{ dayLabel(selection.date) }}</dd></div>
          <div class="flex justify-between">
            <dt class="text-muted">Termin</dt>
            <dd class="font-mono font-semibold">{{ fromMinutes(selection.start) }}–{{ fromMinutes(selection.end) }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-muted">Cena</dt>
            <dd>{{ hoursLabel(selectedMinutes) }} × {{ formatMoney(space.price_per_hour_cents) }}</dd>
          </div>
        </dl>
        <div class="mt-5 flex items-end justify-between border-t border-line pt-5">
          <div>
            <p class="text-lg font-semibold">Skupaj</p>
            <p v-if="fee > 0" class="text-sm text-muted">k obračunu za {{ chargeMonth }}</p>
            <p v-else class="text-sm text-muted">brezplačen prostor</p>
          </div>
          <p class="font-display text-[40px] font-semibold">{{ formatMoney(fee) }}</p>
        </div>
      </template>
      <p v-else class="mt-4 text-muted">Izberite prost termin v koledarju.</p>

      <div v-if="data.usage" class="mt-6">
        <div class="flex justify-between text-[15px]">
          <span>Tedenska omejitev stanovanja</span>
          <span class="font-mono" :class="overLimit ? 'font-semibold text-danger' : ''">
            {{ hoursLabel(data.usage.used_minutes + selectedMinutes).slice(0, -2) }} / {{ data.usage.max_minutes / 60 }} h
          </span>
        </div>
        <div class="mt-2 flex gap-1">
          <span
            v-for="(segment, i) in limitSegments"
            :key="i"
            class="h-2 flex-1 rounded-sm"
            :class="{ 'bg-ink': segment === 'used', 'bg-primary': segment === 'selected', 'bg-subtle': segment === 'free' }"
          />
        </div>
        <p class="mt-2 text-sm text-muted">Štejejo se rezervacije vseh sostanovalcev, pon–ned.</p>
        <p v-if="overLimit" class="field-error">
          Presežena tedenska omejitev. Na voljo je še {{ hoursLabel(data.usage.available_minutes) }}.
        </p>
      </div>

      <div v-if="selection && freeCancelUntil" class="mt-5 flex gap-3 rounded-lg bg-subtle p-4 text-[15px]">
        <AppIcon name="clock" class="mt-0.5 shrink-0" />
        <p>Brezplačen preklic do <strong>{{ freeCancelUntil }}</strong>. Kasneje se zaračuna polna cena.</p>
      </div>

      <p v-if="error" class="mt-4 rounded-lg bg-danger-soft p-3 text-danger">{{ error }}</p>
      <p v-if="success" class="mt-4 rounded-lg bg-[#e7f4ec] p-3 text-success">{{ success }}</p>

      <button class="btn btn-primary mt-6 w-full py-3.5 text-lg" :disabled="!selection || busy || overLimit" @click="submit">
        Rezerviraj termin
      </button>
    </aside>
  </div>
</template>
