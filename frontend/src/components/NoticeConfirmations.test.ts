import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import NoticeConfirmations from './NoticeConfirmations.vue'
import type { LeaseTenant } from '@/api/leases'

const tenant = (id: number, name: string, confirmed: string | null): LeaseTenant => ({
  id,
  user_id: id,
  name,
  email: `${id}@example.com`,
  phone: null,
  rent_share_cents: 30000,
  moved_in_on: '2026-07-01',
  moved_out_on: null,
  notice_confirmed_at: confirmed,
})

describe('NoticeConfirmations', () => {
  it('prikaže, kdo odpovedi še ni potrdil', () => {
    const wrapper = mount(NoticeConfirmations, {
      props: {
        tenants: [tenant(1, 'Jan Potočnik', '2027-03-01T09:00:00Z'), tenant(2, 'Nik Golob', null)],
      },
    })

    const rows = wrapper.findAll('li')
    expect(rows[0].text()).toContain('Jan Potočnik')
    expect(rows[0].text()).not.toContain('še ni potrdil')
    expect(rows[1].text()).toContain('Nik Golob')
    expect(rows[1].text()).toContain('še ni potrdil')
  })
})
