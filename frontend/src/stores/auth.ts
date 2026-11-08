import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { csrf, http } from '@/api/http'

export type Role = 'landlord' | 'tenant' | 'contractor'

export interface Membership {
  building: { id: number; name: string; address: string; apartments_count: number }
  role: Role
  role_label: string
}

export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  memberships: Membership[]
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loaded = ref(false)
  const membershipIndex = ref(0)

  const membership = computed<Membership | null>(
    () => user.value?.memberships[membershipIndex.value] ?? null,
  )
  const role = computed<Role | null>(() => membership.value?.role ?? null)
  const buildingId = computed(() => membership.value?.building.id ?? null)

  async function fetchUser(): Promise<void> {
    try {
      const { data } = await http.get('/api/me')
      user.value = data.data
    } catch {
      user.value = null
    } finally {
      loaded.value = true
    }
  }

  async function login(email: string, password: string): Promise<void> {
    await csrf()
    await http.post('/login', { email, password })
    await fetchUser()
  }

  async function logout(): Promise<void> {
    await http.post('/logout')
    user.value = null
  }

  function selectMembership(index: number): void {
    membershipIndex.value = index
  }

  return { user, loaded, membership, role, buildingId, fetchUser, login, logout, selectMembership }
})
