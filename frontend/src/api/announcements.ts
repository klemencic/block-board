import { http } from './http'

export interface Announcement {
  id: number
  title: string
  body: string
  floor: number | null
  target_label: string
  published_at: string
  author: string
  read_at?: string | null
  delivery?: { recipients: number; sent: number; failed: number }
  informed?: { informed: number; total: number }
}

export interface UninformedApartment {
  id: number
  label: string
  floor: number
  tenants: { name: string; phone: string | null }[]
}

const unwrap = <T>(r: { data: { data: T } }) => r.data.data

export const announcementsApi = {
  list: (buildingId: number) => http.get<{ data: Announcement[] }>(`/api/buildings/${buildingId}/announcements`).then(unwrap),
  get: (id: number) => http.get<{ data: Announcement }>(`/api/announcements/${id}`).then(unwrap),
  publish: (buildingId: number, payload: { title: string; body: string; floor: number | null }) =>
    http.post<{ data: Announcement }>(`/api/buildings/${buildingId}/announcements`, payload).then(unwrap),
  confirmRead: (id: number) => http.post<{ data: Announcement }>(`/api/announcements/${id}/read`).then(unwrap),
  uninformed: (id: number) =>
    http.get<{ data: UninformedApartment[] }>(`/api/announcements/${id}/uninformed`).then(unwrap),
}
