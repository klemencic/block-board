import { http } from './http'
import type { Charge } from './charges'
import type { Lease } from './leases'

export type ApartmentState = 'occupied' | 'in_notice' | 'vacant'

export interface OverviewRow {
  id: number
  label: string
  floor: number
  state: ApartmentState
  lease_id: number | null
  tenants: string[]
  end_date: string | null
  end_type: 'end' | 'termination' | 'indefinite' | null
  expiring: boolean
  overdue_cents: number
  overdue_count: number
  open_repairs: number
}

export interface Overview {
  date: string
  summary: {
    apartments_total: number
    apartments_occupied: number
    overdue_cents: number
    overdue_apartments: number
    overdue_charges: number
    open_repairs: number
    repairs_awaiting_confirmation: number
    expiring_leases: number
    counts: Record<ApartmentState, number>
  }
  apartments: OverviewRow[]
}

export interface ApartmentDetail {
  id: number
  label: string
  floor: number
  area_m2: number
  rooms: number
  current_lease: Lease | null
  leases: Lease[]
  overdue_charges: Charge[]
  open_repairs: { id: number; number: string; title: string; status_label: string }[]
}

export const overviewApi = {
  get: (buildingId: number) => http.get<{ data: Overview }>(`/api/buildings/${buildingId}/overview`).then((r) => r.data.data),
  apartment: (id: number) => http.get<{ data: ApartmentDetail }>(`/api/apartments/${id}`).then((r) => r.data.data),
  badges: (buildingId: number) =>
    http.get<{ data: Record<string, number> }>(`/api/buildings/${buildingId}/badges`).then((r) => r.data.data),
}

export type SortKey = 'label' | 'overdue'

/** Filtriranje in razvrščanje ne spremenita povzetka (US-13, #132). */
export function filterAndSort(rows: OverviewRow[], state: ApartmentState | null, sort: SortKey): OverviewRow[] {
  const filtered = state ? rows.filter((r) => r.state === state) : [...rows]
  return sort === 'overdue'
    ? filtered.sort((a, b) => b.overdue_cents - a.overdue_cents || a.label.localeCompare(b.label))
    : filtered.sort((a, b) => a.label.localeCompare(b.label))
}
