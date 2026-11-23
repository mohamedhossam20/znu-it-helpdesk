<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { api } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useLookupsStore } from '@/stores/lookups'
import { applyLocale, currentLocale, localName } from '@/i18n'
import { assetLabel, locationLabel } from '@/utils/format'

const { t } = useI18n()
const auth = useAuthStore()
const lookups = useLookupsStore()
const router = useRouter()

const assets = ref(null)

onMounted(async () => {
  try {
    assets.value = (await api.context()).data.my_assets
  } catch {
    assets.value = []
  }
})

function switchLanguage() {
  applyLocale(currentLocale() === 'ar' ? 'en' : 'ar')
}

async function logout() {
  await auth.logout()
  lookups.reset()
  router.replace({ name: 'login' })
}
</script>

<template>
  <!-- auth.user becomes null for a moment while logging out, before the redirect. -->
  <section v-if="auth.user" class="space-y-4">
    <div class="card p-5">
      <div class="text-xl font-bold">{{ auth.user.name }}</div>
      <div class="mt-1 text-sm text-slate-600">
        {{ [auth.user.job_title, auth.user.department ? localName(auth.user.department) : null].filter(Boolean).join(' — ') }}
      </div>
      <div class="mt-3 space-y-1 text-sm" dir="ltr" style="text-align: start">
        <div>✉️ {{ auth.user.email }}</div>
        <div v-if="auth.user.extension">☎️ {{ auth.user.extension }}</div>
      </div>
    </div>

    <div class="card p-5">
      <h2 class="mb-2 font-bold">{{ t('profile.fixedLocation') }}</h2>
      <p v-if="auth.user.default_room" class="text-sm">📍 {{ locationLabel(auth.user.default_room) }}</p>
      <p v-else class="text-sm text-slate-500">{{ t('profile.noFixedLocation') }}</p>
      <p class="mt-2 text-xs text-slate-500">{{ t('profile.locationManagedByIt') }}</p>
    </div>

    <div class="card p-5">
      <h2 class="mb-3 font-bold">{{ t('profile.myDevices') }}</h2>
      <div v-if="assets === null" class="h-10 animate-pulse rounded bg-slate-100" />
      <p v-else-if="assets.length === 0" class="text-sm text-slate-500">{{ t('profile.noDevices') }}</p>
      <ul v-else class="space-y-2">
        <li v-for="asset in assets" :key="asset.id" class="flex items-center justify-between gap-3 text-sm">
          <span>{{ localName(asset.type) }} — {{ assetLabel(asset) }}</span>
          <span class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs">{{ asset.asset_tag }}</span>
        </li>
      </ul>
    </div>

    <button class="btn-secondary w-full" @click="switchLanguage">🌐 {{ t('profile.switchLanguage') }}</button>
    <button class="btn-danger w-full" @click="logout">{{ t('profile.logout') }}</button>
  </section>
</template>
