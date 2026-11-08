import { http } from './http'

export type LeaseStatus = 'draft' | 'active' | 'in_notice' | 'ended'

export interface LeaseTenant {
  id: number
  user_id: number
  name: string
  email: string
  phone: string | null
  rent_share_cents: number
  moved_in_on: string
  moved_out_on: string | null
  notice_confirmed_at: string | null
}

export interface Lease {
  id: number
  status: LeaseStatus
  status_label: string
  starts_on: string
  ends_on: string | null
  notice_period_days: number
  monthly_rent_cents: number
  deposit_cents: number
  notice_given_on: string | null
  terminates_on: string | null
  notice_preview_terminates_on: string | null
  building?: { id: number; name: string; address: string } | null
  apartment?: { id: number; label: string; floor: number; building_id: number }
  tenants?: LeaseTenant[]
}

export interface Apartment {
  id: number
  label: string
  floor: number
  area_m2: number
  rooms: number
  occupancy: 'vacant' | 'occupied' | 'in_notice'
  current_lease_id: number | null
}

export interface NewLease {
  starts_on: string
  ends_on: string | null
  notice_period_days: number
  monthly_rent_cents: number
  deposit_cents: number
  tenants: { name: string; email: string; phone: string | null; rent_share_cents: number }[]
}

export const leasesApi = {
  apartments: (buildingId: number) =>
    http.get<{ data: Apartment[] }>(`/api/buildings/${buildingId}/apartments`).then((r) => r.data.data),
  list: (buildingId: number) =>
    http.get<{ data: Lease[] }>(`/api/buildings/${buildingId}/leases`).then((r) => r.data.data),
  mine: () => http.get<{ data: Lease[] }>('/api/me/leases').then((r) => r.data.data),
  get: (id: number) => http.get<{ data: Lease }>(`/api/leases/${id}`).then((r) => r.data.data),
  create: (apartmentId: number, payload: NewLease) =>
    http.post<{ data: Lease }>(`/api/apartments/${apartmentId}/leases`, payload).then((r) => r.data.data),
  giveNotice: (id: number) =>
    http.post<{ data: Lease }>(`/api/leases/${id}/give-notice`).then((r) => r.data.data),
  activate: (id: number) => http.post<{ data: Lease }>(`/api/leases/${id}/activate`).then((r) => r.data.data),
}

/** Nadstropje kot v wireframih: pritličje je »P«. */
export function floorLabel(floor: number): string {
  return floor === 0 ? 'P' : String(floor)
}
