import { describe, expect, it } from 'vitest'
import { filterAndSort, type OverviewRow } from './overview'

const row = (label: string, state: OverviewRow['state'], overdue: number): OverviewRow => ({
  id: label.charCodeAt(0),
  label,
  floor: 0,
  state,
  lease_id: null,
  tenants: [],
  end_date: null,
  end_type: null,
  expiring: false,
  overdue_cents: overdue,
  overdue_count: overdue ? 1 : 0,
  open_repairs: 0,
})

const rows = [row('2A', 'occupied', 30000), row('1C', 'vacant', 0), row('3A', 'occupied', 66000), row('2B', 'in_notice', 0)]

describe('filterAndSort', () => {
  it('filtrira po stanju stanovanja', () => {
    expect(filterAndSort(rows, 'occupied', 'label').map((r) => r.label)).toEqual(['2A', '3A'])
    expect(filterAndSort(rows, null, 'label').map((r) => r.label)).toEqual(['1C', '2A', '2B', '3A'])
  })

  it('razvrsti po znesku zamud', () => {
    expect(filterAndSort(rows, null, 'overdue').map((r) => r.label)).toEqual(['3A', '2A', '1C', '2B'])
  })

  it('ne spremeni izvornih podatkov, iz katerih je izračunan povzetek', () => {
    filterAndSort(rows, 'vacant', 'overdue')
    expect(rows.map((r) => r.label)).toEqual(['2A', '1C', '3A', '2B'])
  })
})
