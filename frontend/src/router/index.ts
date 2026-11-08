import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import { homeFor } from '@/navigation'
import { useAuthStore, type Role } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    roles?: Role[]
    public?: boolean
  }
}

const soon = () => import('@/views/ComingSoonView.vue')

const appRoutes: RouteRecordRaw[] = [
  { path: 'pregled', component: soon, meta: { title: 'Pregled stavbe', roles: ['landlord'] } },
  { path: 'najemi', component: () => import('@/views/landlord/LeasesView.vue'), meta: { roles: ['landlord'] } },
  {
    path: 'najemi/nov',
    component: () => import('@/views/landlord/LeaseCreateView.vue'),
    meta: { roles: ['landlord'] },
  },
  {
    path: 'najemi/:id(\\d+)',
    component: () => import('@/views/landlord/LeaseDetailView.vue'),
    meta: { roles: ['landlord'] },
  },
  { path: 'obveznosti', component: () => import('@/views/landlord/ChargesView.vue'), meta: { roles: ['landlord'] } },
  { path: 'poloznice', component: () => import('@/views/landlord/BillsView.vue'), meta: { roles: ['landlord'] } },
  { path: 'register', component: soon, meta: { title: 'Register najemnikov', roles: ['landlord'] } },
  { path: 'moj-najem', component: () => import('@/views/tenant/MyLeaseView.vue'), meta: { roles: ['tenant'] } },
  {
    path: 'moje-obveznosti',
    component: () => import('@/views/tenant/MyChargesView.vue'),
    meta: { roles: ['tenant'] },
  },
  { path: 'prostori', component: () => import('@/views/SpacesView.vue'), meta: { roles: ['landlord', 'tenant'] } },
  { path: 'okvare', component: () => import('@/views/repairs/RepairsView.vue'), meta: { roles: ['landlord', 'tenant'] } },
  {
    path: 'okvare/nova',
    component: () => import('@/views/repairs/RepairCreateView.vue'),
    meta: { roles: ['landlord', 'tenant'] },
  },
  {
    path: 'okvare/:id(\\d+)',
    component: () => import('@/views/repairs/RepairDetailView.vue'),
    meta: { roles: ['landlord', 'tenant', 'contractor'] },
  },
  { path: 'nalogi', component: soon, meta: { title: 'Moji nalogi', roles: ['contractor'] } },
  { path: 'obvestila', component: soon, meta: { title: 'Obvestila', roles: ['landlord', 'tenant'] } },
]

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/prijava', component: () => import('@/views/LoginView.vue'), meta: { public: true } },
    { path: '/povabilo/:token', component: () => import('@/views/InvitationView.vue'), meta: { public: true } },
    { path: '/', component: AppLayout, children: appRoutes },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.loaded) await auth.fetchUser()

  if (to.meta.public) return auth.user ? homeFor(auth.role) : true
  if (!auth.user) return '/prijava'
  if (to.path === '/') return homeFor(auth.role)
  // SPA skriva strani zaradi preglednosti, pravice dejansko preveri API.
  if (to.meta.roles && auth.role && !to.meta.roles.includes(auth.role)) return homeFor(auth.role)
  return true
})
