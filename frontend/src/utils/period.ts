const DAY = 86_400_000

function toUtc(date: string): number {
  const [y, m, d] = date.slice(0, 10).split('-').map(Number)
  return Date.UTC(y, m - 1, d)
}

/** Število dni med datumoma, vključno z obema (1. 9.–30. 9. = 30). */
export function inclusiveDays(start: string, end: string): number {
  return Math.round((toUtc(end) - toUtc(start)) / DAY) + 1
}

/**
 * Del obdobja, v katerem je sostanovalec bival, kot odmik in širina v odstotkih
 * (za trak »Bival v obdobju« v predogledu položnice).
 */
export function residenceBar(
  periodStart: string,
  periodEnd: string,
  movedIn: string | null,
  movedOut: string | null,
): { offset: number; width: number } {
  const total = inclusiveDays(periodStart, periodEnd)
  const from = movedIn && movedIn > periodStart ? movedIn : periodStart
  const to = movedOut && movedOut < periodEnd ? movedOut : periodEnd
  const offset = inclusiveDays(periodStart, from) - 1
  const width = Math.max(0, inclusiveDays(from, to))
  return { offset: (offset / total) * 100, width: (width / total) * 100 }
}
