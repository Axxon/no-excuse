import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from './stores/auth'
import HomeView from './views/HomeView.vue'
import LoginView from './views/LoginView.vue'
import DashboardView from './views/DashboardView.vue'
import CampaignView from './views/CampaignView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: HomeView },
    { path: '/login', component: LoginView },
    { path: '/dashboard', component: DashboardView, meta: { requiresAuth: true } },
    { path: '/dashboard/offers/:uuid', component: CampaignView, meta: { requiresAuth: true } },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isAuthenticated) return '/login'
  if (auth.isAuthenticated) {
    try { await auth.loadUser() } catch { await auth.logout(); return '/login' }
  }
  return true
})
