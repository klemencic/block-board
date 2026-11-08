/** Znesek v centih kot »1.260,00 €«. */
export function formatMoney(cents: number): string {
  const negative = cents < 0
  const abs = Math.abs(cents)
  const euros = Math.floor(abs / 100)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  const rest = String(abs % 100).padStart(2, '0')
  return `${negative ? '−' : ''}${euros},${rest} €`
}

/** Datum ISO (2026-10-21) kot »21. 10. 2026«. */
export function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  const [y, m, d] = value.slice(0, 10).split('-').map(Number)
  return `${d}. ${m}. ${y}`
}

/** Datum in čas kot »20. 10. 2026 · 08:14«. */
export function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—'
  const date = new Date(value)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getDate()}. ${date.getMonth() + 1}. ${date.getFullYear()} · ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const MONTHS = [
  'januar', 'februar', 'marec', 'april', 'maj', 'junij',
  'julij', 'avgust', 'september', 'oktober', 'november', 'december',
]

/** Mesec obračuna (2027-05-01) kot »maj 2027«. */
export function formatPeriod(value: string): string {
  const [y, m] = value.split('-').map(Number)
  return `${MONTHS[m - 1]} ${y}`
}

export function monthName(month: number): string {
  return MONTHS[month - 1]
}

/** Vnos uporabnika »90,00« ali »90.5« v cente. */
export function parseMoney(input: string): number | null {
  const normalized = input.trim().replace(/\./g, '').replace(',', '.')
  if (normalized === '' || Number.isNaN(Number(normalized))) return null
  return Math.round(Number(normalized) * 100)
}

/** Slovenska dvojina in množina: 1 dan, 2 dneva, 3 dnevi, 5 dni (tudi 101 dan, 102 dneva …). */
export function daysLabel(days: number): string {
  const mod = days % 100
  const word = mod === 1 ? 'dan' : mod === 2 ? 'dneva' : mod === 3 || mod === 4 ? 'dnevi' : 'dni'
  return `${days} ${word}`
}

export function todayIso(): string {
  const now = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}
