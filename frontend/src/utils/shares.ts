import { formatMoney } from './format'

/**
 * Sporočilo o razliki med vsoto deležev in najemnino (US-01, kriterij 2).
 * Vrne null, ko se vsota ujema.
 */
export function shareDifferenceMessage(rentCents: number, shareCents: number[]): string | null {
  const diff = shareCents.reduce((sum, share) => sum + share, 0) - rentCents
  if (diff === 0) return null
  return `Deleži so za ${formatMoney(Math.abs(diff))} ${diff < 0 ? 'nižji' : 'višji'} od najemnine.`
}
