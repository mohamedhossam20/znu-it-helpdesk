<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { ApiError } from '@/api/http'

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const remember = ref(true)
const busy = ref(false)
const error = ref('')

async function submit() {
  error.value = ''
  busy.value = true
  try {
    await auth.login(email.value.trim(), password.value, remember.value)
    const target = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/'
    router.replace(target)
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) error.value = e.field('email') || t('login.failed')
    else if (e instanceof ApiError && e.status === 429) error.value = t('login.throttled')
    else error.value = t('common.networkError')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="flex min-h-dvh items-center justify-center bg-gradient-to-b from-brand-50 to-slate-50 px-4">
    <form class="card w-full max-w-sm space-y-5 p-6" @submit.prevent="submit">
      <div class="text-center">
        <img src="/icons/icon.svg" alt="" class="mx-auto mb-3 size-16" />
        <h1 class="text-xl font-bold">{{ t('app.title') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ t('login.subtitle') }}</p>
      </div>

      <p v-if="route.query.redirect?.startsWith?.('/r/')" class="rounded-xl bg-brand-50 p-3 text-sm text-brand-800">
        {{ t('login.afterQr') }}
      </p>

      <label class="block">
        <span class="label">{{ t('login.email') }}</span>
        <input v-model="email" type="email" class="field" autocomplete="username" dir="ltr" required />
      </label>

      <label class="block">
        <span class="label">{{ t('login.password') }}</span>
        <input v-model="password" type="password" class="field" autocomplete="current-password" dir="ltr" required />
      </label>

      <label class="flex items-center gap-2 text-sm">
        <input v-model="remember" type="checkbox" class="size-4 accent-brand-700" />
        {{ t('login.remember') }}
      </label>

      <p v-if="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-800" role="alert">{{ error }}</p>

      <button type="submit" class="btn-primary w-full" :disabled="busy">
        {{ busy ? t('common.loading') : t('login.submit') }}
      </button>
    </form>
  </div>
</template>
