<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import BrandLogo from '@/components/BrandLogo.vue'
import { validationErrors } from '@/api/http'
import { homeFor } from '@/navigation'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref<string | null>(null)
const busy = ref(false)

const demo = [
  { label: 'Lastnik', email: 'lastnik@blockboard.test' },
  { label: 'Najemnik', email: 'jan@blockboard.test' },
  { label: 'Izvajalec', email: 'breznik@blockboard.test' },
]

async function submit() {
  busy.value = true
  error.value = null
  try {
    await auth.login(email.value, password.value)
    router.push(homeFor(auth.role))
  } catch (e) {
    error.value = validationErrors(e).email?.[0] ?? 'Prijava ni uspela.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="grid min-h-screen lg:grid-cols-[420px_1fr]">
    <aside class="hidden flex-col justify-between bg-sidebar p-10 lg:flex">
      <BrandLogo />
      <p class="font-display text-3xl leading-snug text-white">
        Celotno najemno razmerje na enem mestu — od vselitve do vračila varščine.
      </p>
      <p class="text-sm text-sidebar-muted">Lastnik · Najemnik · Izvajalec del</p>
    </aside>

    <main class="flex items-center justify-center p-6">
      <form class="card w-full max-w-md p-8" @submit.prevent="submit">
        <p class="eyebrow">Prijava</p>
        <h1 class="mt-1 text-3xl font-semibold">Dobrodošli nazaj</h1>

        <label class="field-label mt-6" for="email">E-naslov</label>
        <input id="email" v-model="email" type="email" class="field-input" autocomplete="username" required />

        <label class="field-label mt-4" for="password">Geslo</label>
        <input
          id="password"
          v-model="password"
          type="password"
          class="field-input"
          autocomplete="current-password"
          required
        />

        <p v-if="error" class="field-error">{{ error }}</p>

        <button class="btn btn-primary mt-6 w-full" :disabled="busy">Prijava</button>

        <div class="mt-6 rounded-lg bg-subtle p-4 text-sm">
          <p class="font-semibold">Demo računi (geslo: geslo123)</p>
          <button
            v-for="account in demo"
            :key="account.email"
            type="button"
            class="mt-1 block text-primary underline"
            @click="email = account.email; password = 'geslo123'"
          >
            {{ account.label }} · {{ account.email }}
          </button>
        </div>
      </form>
    </main>
  </div>
</template>
