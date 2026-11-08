import { http } from './http'

export interface SharedSpace {
  id: number
  name: string
  price_per_hour_cents: number
  open_from: string
  open_to: string
  max_hours_per_week: number
  free_cancel_hours: number
}

export interface CalendarBooking {
  id: number
  starts_at: string
  ends_at: string
  own: boolean
  apartment: string | null
}

export interface Availability {
  space: SharedSpace
  week_start: string
  week_end: string
  bookings: CalendarBooking[]
  usage: { used_minutes: number; max_minutes: number; available_minutes: number; apartment: string } | null
  can_book: boolean
}

export const spacesApi = {
  list: (buildingId: number) =>
    http.get<{ data: SharedSpace[] }>(`/api/buildings/${buildingId}/shared-spaces`).then((r) => r.data.data),
  availability: (spaceId: number, week: string) =>
    http.get<{ data: Availability }>(`/api/shared-spaces/${spaceId}/availability`, { params: { week } }).then((r) => r.data.data),
  book: (spaceId: number, startsAt: string, endsAt: string) =>
    http
      .post<{ data: { id: number; fee_cents: number; charge_period_label: string | null } }>(
        `/api/shared-spaces/${spaceId}/bookings`,
        { starts_at: startsAt, ends_at: endsAt },
      )
      .then((r) => r.data.data),
}
