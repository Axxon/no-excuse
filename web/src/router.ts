import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from './stores/auth'
import HomeView from './views/HomeView.vue'
import LoginView from './views/LoginView.vue'
import DashboardView from './views/DashboardView.vue'
import CampaignView from './views/CampaignView.vue'
import SetupView from './views/SetupView.vue'
import SettingsView from './views/SettingsView.vue'
import { apiRequest, type SetupStatus } from './api'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: HomeView },
    { path: '/setup', component: SetupView },
    { path: '/login', component: LoginView },
    { path: '/dashboard', component: DashboardView, meta: { requiresAuth: true } },
    { path: '/dashboard/offers/:uuid', component: CampaignView, meta: { requiresAuth: true } },
    { path: '/settings', component: SettingsView, meta: { requiresAuth: true } },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  try {
    const setup = await apiRequest<SetupStatus>('/setup/status')
    if (!setup.configured && to.path !== '/setup') return '/setup'
    if (setup.configured && to.path === '/setup') return auth.isAuthenticated ? '/dashboard' : '/login'
  } catch { /* L'écran cible affichera l'indisponibilité API. */ }
  if (to.meta.requiresAuth && !auth.isAuthenticated) return '/login'
  if (auth.isAuthenticated) {
    try { await auth.loadUser() } catch { await auth.logout(); return '/login' }
  }
  return true
})
