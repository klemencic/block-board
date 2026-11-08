<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/AppIcon.vue'
import BrandLogo from '@/components/BrandLogo.vue'
import { navigation } from '@/navigation'
import { useAuthStore } from '@/stores/auth'
import { useBadgesStore } from '@/stores/badges'

const auth = useAuthStore()
const badges = useBadgesStore()
const router = useRouter()

const items = computed(() => (auth.role ? navigation[auth.role] : []))

const subtitle = computed(() => {
  const m = auth.membership
  if (!m) return ''
  if (m.role === 'landlord') return `${m.building.apartments_count} stanovanj · ${m.role_label}`
  return `${auth.user?.name} · ${m.role_label}`
})

async function logout() {
  await auth.logout()
  router.push('/prijava')
}
</script>

<template>
  <div class="flex min-h-screen">
    <aside class="sticky top-0 flex h-screen w-[260px] shrink-0 flex-col bg-sidebar px-4 py-7 text-sidebar-text">
      <div class="px-3"><BrandLogo /></div>

      <div v-if="auth.membership" class="mt-9 px-3">
        <p class="text-[11px] tracking-[0.14em] text-sidebar-muted uppercase">
          {{ auth.role === 'tenant' ? 'Moj najem' : 'Stavba' }}
        </p>
        <p class="mt-1 text-[17px] font-semibold text-white">
          {{ auth.membership.building.name
          }}<template v-if="badges.apartmentLabel"> · {{ badges.apartmentLabel }}</template>
        </p>
        <p class="mt-0.5 text-sm text-sidebar-muted">{{ subtitle }}</p>
        <select
          v-if="(auth.user?.memberships.length ?? 0) > 1"
          class="mt-3 w-full rounded-md bg-sidebar-active px-2 py-1.5 text-sm text-white"
          @change="auth.selectMembership(Number(($event.target as HTMLSelectElement).value)); router.push('/')"
        >
          <option v-for="(m, i) in auth.user?.memberships" :key="i" :value="i">
            {{ m.building.name }} · {{ m.role_label }}
          </option>
        </select>
      </div>

      <nav class="mt-8 flex flex-1 flex-col gap-1">
        <RouterLink
          v-for="item in items"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-[16px] text-sidebar-text transition hover:bg-sidebar-active"
          active-class="bg-sidebar-active font-semibold text-white"
        >
          <AppIcon :name="item.icon" />
          <span class="flex-1">{{ item.label }}</span>
          <span
            v-if="badges.counts[item.to]"
            class="grid size-6 place-items-center rounded-full bg-badge text-xs font-semibold text-white"
          >
            {{ badges.counts[item.to] }}
          </span>
        </RouterLink>
      </nav>

      <button
        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-sidebar-muted hover:bg-sidebar-active hover:text-white"
        @click="logout"
      >
        <AppIcon name="logout" :size="18" /> Odjava
      </button>
    </aside>

    <main class="min-w-0 flex-1 px-12 py-10">
      <RouterView />
    </main>
  </div>
</template>
