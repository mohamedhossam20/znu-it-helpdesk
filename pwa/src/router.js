import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  { path: '/login', name: 'login', component: () => import('@/pages/LoginPage.vue'), meta: { guest: true } },
  { path: '/', name: 'home', component: () => import('@/pages/HomePage.vue') },
  { path: '/tickets/new', name: 'new-ticket', component: () => import('@/pages/NewTicketPage.vue') },
  // Room QR stickers point here.
  { path: '/r/:token', name: 'qr', component: () => import('@/pages/NewTicketPage.vue'), props: true },
  { path: '/tickets', name: 'tickets', component: () => import('@/pages/TicketsPage.vue') },
  { path: '/tickets/:number', name: 'ticket', component: () => import('@/pages/TicketPage.vue'), props: true },
  { path: '/profile', name: 'profile', component: () => import('@/pages/ProfilePage.vue') },
  { path: '/notifications', name: 'notifications', component: () => import('@/pages/NotificationsPage.vue') },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.loaded) await auth.load()

  if (to.meta.guest) {
    return auth.isLoggedIn ? { name: 'home' } : true
  }

  if (!auth.isLoggedIn) {
    // Come back to the scanned room (or any page) after signing in.
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  return true
})
