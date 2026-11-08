import { describe, expect, it } from 'vitest'
import { daysLabel, formatDate, formatMoney, formatPeriod, parseMoney } from './format'

describe('formatMoney', () => {
  it('izpiše zneske v slovenski obliki', () => {
    expect(formatMoney(96000)).toBe('960,00 €')
    expect(formatMoney(126000)).toBe('1.260,00 €')
    expect(formatMoney(15484)).toBe('154,84 €')
    expect(formatMoney(5)).toBe('0,05 €')
  })

  it('negativen znesek (dobropis) označi z minusom', () => {
    expect(formatMoney(-5000)).toBe('−50,00 €')
  })
})

describe('formatDate', () => {
  it('izpiše datum brez vodilnih ničel', () => {
    expect(formatDate('2026-10-21')).toBe('21. 10. 2026')
    expect(formatDate('2027-03-01T00:00:00Z')).toBe('1. 3. 2027')
    expect(formatDate(null)).toBe('—')
  })
})

describe('formatPeriod', () => {
  it('izpiše mesec obračuna', () => {
    expect(formatPeriod('2027-05-01')).toBe('maj 2027')
  })
})

describe('daysLabel', () => {
  it('uporabi pravilno slovensko obliko', () => {
    expect(daysLabel(1)).toBe('1 dan')
    expect(daysLabel(2)).toBe('2 dneva')
    expect(daysLabel(4)).toBe('4 dnevi')
    expect(daysLabel(12)).toBe('12 dni')
    expect(daysLabel(101)).toBe('101 dan')
  })
})

describe('parseMoney', () => {
  it('pretvori vnos v cente', () => {
    expect(parseMoney('90,00')).toBe(9000)
    expect(parseMoney('1.260,50')).toBe(126050)
    expect(parseMoney('abc')).toBeNull()
    expect(parseMoney('')).toBeNull()
  })
})
