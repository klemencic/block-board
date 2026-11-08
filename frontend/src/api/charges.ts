import { http } from './http'

export type ChargeType = 'rent' | 'utility' | 'booking' | 'repair' | 'other'
export type ChargeStatus = 'open' | 'paid' | 'overdue'

export interface Charge {
  id: number
  type: ChargeType
  type_label: string
  description: string
  amount_cents: number
  period: string
  due_date: string
  paid_at: string | null
  status: ChargeStatus
  days_overdue: number
  has_source: boolean
  tenant: { id: number; name: string } | null
  apartment: { id: number; label: string } | null
}

export interface ChargeMonth {
  period: string
  total_cents: number
  open_cents: number
  charges: Charge[]
}

export interface ChargeSource {
  type: 'utility' | 'booking' | 'repair'
  data: Record<string, any>
}

export const chargesApi = {
  mine: (buildingId?: number | null) =>
    http
      .get<{ data: ChargeMonth[] }>('/api/me/charges', { params: { building_id: buildingId ?? undefined } })
      .then((r) => r.data.data),
  get: (id: number) => http.get<{ data: Charge }>(`/api/charges/${id}`).then((r) => r.data.data),
  source: (id: number) => http.get<{ data: ChargeSource }>(`/api/charges/${id}/source`).then((r) => r.data.data),
}
