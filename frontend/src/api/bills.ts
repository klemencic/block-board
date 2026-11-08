import { http } from './http'

export type UtilityType = 'electricity' | 'water' | 'heating' | 'waste' | 'internet' | 'other'
export type SplitMethod = 'area' | 'persons' | 'equal'

export const utilityTypes: { value: UtilityType; label: string }[] = [
  { value: 'electricity', label: 'Elektrika' },
  { value: 'water', label: 'Voda' },
  { value: 'heating', label: 'Ogrevanje' },
  { value: 'waste', label: 'Odpadki' },
  { value: 'internet', label: 'Internet' },
  { value: 'other', label: 'Drugo' },
]

export const splitMethods: { value: SplitMethod; label: string }[] = [
  { value: 'area', label: 'po površini' },
  { value: 'persons', label: 'po številu oseb' },
  { value: 'equal', label: 'na enake dele' },
]

export interface BillPayload {
  scope: 'apartment' | 'building'
  apartment_id: number | null
  type: UtilityType
  amount_cents: number
  period_start: string
  period_end: string
  split_method: SplitMethod | null
}

export interface PreviewShare {
  lease_tenant_id: number | null
  name: string
  moved_in_on: string | null
  moved_out_on: string | null
  days: number
  amount_cents: number
}

export interface PreviewApartment {
  id: number
  label: string
  floor: number
  area_m2: number
  amount_cents: number
  days: number
  landlord_share: boolean
  shares: PreviewShare[]
}

export interface BillPreview {
  days_in_period: number
  total_cents: number
  matches_bill: boolean
  charge_period: string
  charge_period_label: string
  apartments: PreviewApartment[]
}

export interface UtilityBill {
  id: number
  type: UtilityType
  type_label: string
  scope: 'apartment' | 'building'
  apartment: { id: number; label: string } | null
  amount_cents: number
  period_start: string
  period_end: string
  split_method: SplitMethod | null
  split_method_label: string | null
}

export const billsApi = {
  list: (buildingId: number) =>
    http.get<{ data: UtilityBill[] }>(`/api/buildings/${buildingId}/utility-bills`).then((r) => r.data.data),
  preview: (buildingId: number, payload: BillPayload) =>
    http.post<{ data: BillPreview }>(`/api/buildings/${buildingId}/utility-bills/preview`, payload).then((r) => r.data.data),
  store: (buildingId: number, payload: BillPayload) =>
    http.post<{ data: UtilityBill }>(`/api/buildings/${buildingId}/utility-bills`, payload).then((r) => r.data.data),
}
