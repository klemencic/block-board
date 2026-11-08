import { describe, expect, it } from 'vitest'
import { inclusiveDays, residenceBar } from './period'

describe('inclusiveDays', () => {
  it('šteje oba robna dneva', () => {
    expect(inclusiveDays('2026-09-01', '2026-09-30')).toBe(30)
    expect(inclusiveDays('2026-09-16', '2026-09-30')).toBe(15)
    expect(inclusiveDays('2028-02-01', '2028-02-29')).toBe(29)
  })
})

describe('residenceBar', () => {
  it('celo obdobje zapolni trak', () => {
    expect(residenceBar('2026-09-01', '2026-09-30', '2026-07-01', null)).toEqual({ offset: 0, width: 100 })
  })

  it('vselitev 16. 9. zapolni drugo polovico', () => {
    expect(residenceBar('2026-09-01', '2026-09-30', '2026-09-16', null)).toEqual({ offset: 50, width: 50 })
  })
})
