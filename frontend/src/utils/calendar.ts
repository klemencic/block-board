const pad = (n: number) => String(n).padStart(2, '0')

export const DAY_NAMES = ['Pon', 'Tor', 'Sre', 'Čet', 'Pet', 'Sob', 'Ned']
const DAY_NAMES_LOWER = ['pon', 'tor', 'sre', 'čet', 'pet', 'sob', 'ned']

/** Lokalni datum kot YYYY-MM-DD. */
export function isoDate(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

export function parseDate(iso: string): Date {
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d)
}

export function addDays(iso: string, days: number): string {
  const date = parseDate(iso)
  date.setDate(date.getDate() + days)
  return isoDate(date)
}

/** Ponedeljek tedna, v katerem je podani dan. */
export function mondayOf(iso: string): string {
  const date = parseDate(iso)
  const weekday = (date.getDay() + 6) % 7
  return addDays(iso, -weekday)
}

/** »08:30« → 510 */
export function toMinutes(time: string): number {
  const [h, m] = time.split(':').map(Number)
  return h * 60 + m
}

/** 510 → »08:30« */
export function fromMinutes(minutes: number): string {
  return `${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}`
}

/** »Teden 19.–25. 10. 2026« (ali čez mesec »Teden 26. 10.–1. 11. 2026«). */
export function weekLabel(start: string, end: string): string {
  const s = parseDate(start)
  const e = parseDate(end)
  const left = s.getMonth() === e.getMonth() ? `${s.getDate()}.` : `${s.getDate()}. ${s.getMonth() + 1}.`
  return `Teden ${left}–${e.getDate()}. ${e.getMonth() + 1}. ${e.getFullYear()}`
}

/** »Čet, 22. 10. 2026« */
export function dayLabel(iso: string, capitalize = true): string {
  const date = parseDate(iso)
  const name = (capitalize ? DAY_NAMES : DAY_NAMES_LOWER)[(date.getDay() + 6) % 7]
  return `${name}, ${date.getDate()}. ${date.getMonth() + 1}. ${date.getFullYear()}`
}

/** Ure kot »3 h« ali »1,5 h«. */
export function hoursLabel(minutes: number): string {
  const hours = minutes / 60
  return `${Number.isInteger(hours) ? hours : hours.toFixed(1).replace('.', ',')} h`
}
