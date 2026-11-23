import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { router } from './router'
import { applyLocale, currentLocale, i18n } from './i18n'
import { onUnauthorized, useLocaleHeader } from './api/http'
import { useAuthStore } from './stores/auth'
import './style.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia).use(i18n).use(router)

applyLocale(currentLocale())
useLocaleHeader(currentLocale)

// Session expired or logged out elsewhere: back to the login screen.
onUnauthorized(() => {
  const auth = useAuthStore(pinia)
  const wasLoggedIn = auth.isLoggedIn
  auth.clear()
  if (wasLoggedIn && router.currentRoute.value.name !== 'login') {
    router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
  }
})

app.mount('#app')
