<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { apiRequest } from './api'
import { useAuthStore } from './stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()
async function resetDemo(): Promise<void> {
  const payload = await apiRequest<{ offer_uuid: string }>('/demo/reset', { method: 'POST' }, auth.token)
  await router.push(`/dashboard/offers/${payload.offer_uuid}`)
}
</script>

<template>
  <div class="site-shell">
    <header class="topbar">
      <RouterLink class="brand" to="/">
        <span class="brand-mark">n/e</span>
        <span>no-excuse</span>
      </RouterLink>
      <nav class="main-nav" :aria-label="t('nav.main')">
        <RouterLink v-if="auth.isAuthenticated" to="/dashboard">{{ t('nav.dashboard') }}</RouterLink>
        <RouterLink v-if="auth.isAuthenticated && !auth.user?.organization?.is_demo" to="/settings">{{ t('nav.settings') }}</RouterLink>
        <RouterLink v-else class="button button-small button-ghost" to="/login">{{ t('nav.recruiter') }}</RouterLink>
      </nav>
    </header>
    <aside v-if="auth.user?.organization?.is_demo" class="demo-banner"><div><strong>{{ t('demo.banner') }}</strong><span>{{ t('demo.bannerLead') }}</span></div><button class="button button-small button-ghost" @click="resetDemo">{{ t('demo.reset') }}</button></aside>
    <main>
      <RouterView />
    </main>
    <footer class="footer">
      <strong>no-excuse</strong>
      <span>{{ t('footer.promise') }}</span>
      <a href="https://github.com/Axxon/no-excuse" target="_blank" rel="noreferrer">GitHub</a>
      <RouterLink to="/about">À propos</RouterLink>
    </footer>
  </div>
</template>
