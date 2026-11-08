<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import BrandLogo from '@/components/BrandLogo.vue'
import { csrf, http, validationErrors, type ValidationErrors } from '@/api/http'

const route = useRoute()
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const errors = ref<ValidationErrors>({})
const done = ref(false)
const busy = ref(false)

async function submit() {
  busy.value = true
  errors.value = {}
  try {
    await csrf()
    await http.post('/api/invitations/accept', {
      token: route.params.token,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    done.value = true
  } catch (e) {
    errors.value = validationErrors(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="grid min-h-screen place-items-center bg-sidebar p-6">
    <div class="w-full max-w-md">
      <div class="mb-6"><BrandLogo /></div>
      <div class="card p-8">
        <template v-if="done">
          <h1 class="text-3xl font-semibold">Geslo je nastavljeno</h1>
          <p class="mt-3 text-muted">Zdaj se lahko prijavite in vidite svoj najem.</p>
          <RouterLink to="/prijava" class="btn btn-primary mt-6 w-full">Na prijavo</RouterLink>
        </template>
        <form v-else @submit.prevent="submit">
          <p class="eyebrow">Povabilo</p>
          <h1 class="mt-1 text-3xl font-semibold">Nastavite geslo</h1>
          <p class="mt-2 text-[15px] text-muted">Vpišite e-naslov, na katerega ste prejeli povabilo.</p>

          <label class="field-label mt-6" for="email">E-naslov</label>
          <input id="email" v-model="email" type="email" class="field-input" required />
          <p v-if="errors.email" class="field-error">{{ errors.email[0] }}</p>

          <label class="field-label mt-4" for="password">Novo geslo</label>
          <input id="password" v-model="password" type="password" class="field-input" required />
          <p v-if="errors.password" class="field-error">{{ errors.password[0] }}</p>

          <label class="field-label mt-4" for="password2">Ponovite geslo</label>
          <input id="password2" v-model="passwordConfirmation" type="password" class="field-input" required />

          <p v-if="errors.token" class="field-error mt-4">{{ errors.token[0] }}</p>
          <button class="btn btn-primary mt-6 w-full" :disabled="busy">Nastavi geslo</button>
        </form>
      </div>
    </div>
  </div>
</template>
