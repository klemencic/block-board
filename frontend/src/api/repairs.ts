import { http } from './http'

export type RepairStatus = 'reported' | 'confirmed' | 'rejected' | 'assigned' | 'scheduled' | 'done' | 'closed'
export type Payer = 'landlord' | 'tenant'

export interface TimelineEvent {
  id: number
  kind: 'status' | 'payer' | 'note'
  from_status: RepairStatus | null
  to_status: RepairStatus
  note: string | null
  meta: Record<string, any> | null
  user: string
  created_at: string
}

export interface Repair {
  id: number
  number: string
  title: string
  description: string
  status: RepairStatus
  status_label: string
  category: { id: number; name: string }
  payer: Payer
  payer_label: string
  default_payer: Payer
  location: {
    label: string
    apartment: { id: number; label: string; floor: number } | null
    shared_space: { id: number; name: string } | null
  }
  building: { name: string; address: string }
  reporter: { id: number; name: string; phone: string | null }
  contractor: { id: number; name: string; phone: string | null } | null
  scheduled_at: string | null
  cost_cents: number | null
  closed_at: string | null
  created_at: string
  photos: { id: number; name: string; context: 'report' | 'completion'; url: string }[]
  timeline?: TimelineEvent[]
  permissions: { manage: boolean; perform: boolean }
}

export interface RepairForm {
  categories: { id: number; name: string; default_payer: Payer; default_payer_label: string }[]
  apartments: { id: number; label: string; floor: number }[]
  shared_spaces: { id: number; name: string }[]
  max_photos: number
  max_photo_mb: number
}

const unwrap = <T>(r: { data: { data: T } }) => r.data.data

export const repairsApi = {
  list: (buildingId: number) => http.get<{ data: Repair[] }>(`/api/buildings/${buildingId}/repair-requests`).then(unwrap),
  form: (buildingId: number) => http.get<{ data: RepairForm }>(`/api/buildings/${buildingId}/repair-form`).then(unwrap),
  get: (id: number) => http.get<{ data: Repair }>(`/api/repair-requests/${id}`).then(unwrap),
  create: (buildingId: number, form: FormData) =>
    http.post<{ data: Repair }>(`/api/buildings/${buildingId}/repair-requests`, form).then(unwrap),
  action: (id: number, action: string, payload: Record<string, unknown> | FormData = {}) =>
    http.post<{ data: Repair }>(`/api/repair-requests/${id}/${action}`, payload).then(unwrap),
}

export const STEPS: { status: RepairStatus; label: string }[] = [
  { status: 'reported', label: 'Prijavljeno' },
  { status: 'confirmed', label: 'Potrjeno' },
  { status: 'assigned', label: 'Dodeljeno' },
  { status: 'scheduled', label: 'Termin' },
  { status: 'done', label: 'Opravljeno' },
  { status: 'closed', label: 'Zaključeno' },
]

/** Naslov koraka v časovnici. */
export function eventTitle(event: TimelineEvent): string {
  if (event.kind === 'payer') {
    const label = (p: string) => (p === 'tenant' ? 'najemnik' : 'lastnik')
    return event.meta?.from
      ? `Plačnik: ${label(event.meta.from)} → ${label(event.meta.payer)}`
      : `Privzeti plačnik: ${label(event.meta?.payer)}`
  }
  if (event.from_status === 'done' && event.to_status === 'scheduled') return 'Vrnjeno v popravek'
  return (
    {
      reported: 'Okvara prijavljena',
      confirmed: 'Prijava potrjena',
      rejected: 'Prijava zavrnjena',
      assigned: 'Dodeljeno izvajalcu',
      scheduled: 'Dogovorjen termin',
      done: 'Delo opravljeno',
      closed: 'Prijava zaključena',
    } as Record<RepairStatus, string>
  )[event.to_status]
}

export const statusTone: Record<RepairStatus, 'warning' | 'neutral' | 'primary' | 'danger' | 'success' | 'dashed'> = {
  reported: 'warning',
  confirmed: 'primary',
  rejected: 'danger',
  assigned: 'primary',
  scheduled: 'primary',
  done: 'warning',
  closed: 'success',
}
