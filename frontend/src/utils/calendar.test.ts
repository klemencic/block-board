import { describe, expect, it } from 'vitest'
import { addDays, dayLabel, fromMinutes, hoursLabel, mondayOf, toMinutes, weekLabel } from './calendar'

describe('koledar', () => {
  it('poišče ponedeljek tedna', () => {
    expect(mondayOf('2026-10-21')).toBe('2026-10-19')
    expect(mondayOf('2026-10-25')).toBe('2026-10-19')
    expect(mondayOf('2026-10-19')).toBe('2026-10-19')
  })

  it('izpiše teden kot v wireframu', () => {
    expect(weekLabel('2026-10-19', '2026-10-25')).toBe('Teden 19.–25. 10. 2026')
    expect(weekLabel('2026-10-26', addDays('2026-10-26', 6))).toBe('Teden 26. 10.–1. 11. 2026')
  })

  it('pretvarja čas in ure', () => {
    expect(toMinutes('18:30')).toBe(1110)
    expect(fromMinutes(1110)).toBe('18:30')
    expect(hoursLabel(180)).toBe('3 h')
    expect(hoursLabel(90)).toBe('1,5 h')
    expect(dayLabel('2026-10-22')).toBe('Čet, 22. 10. 2026')
  })
})
