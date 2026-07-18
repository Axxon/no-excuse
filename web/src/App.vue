<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { apiRequest, type DemoStatus } from './api'
import { useAuthStore } from './stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const publicDemo = ref(false)
onMounted(async () => { try { publicDemo.value = (await apiRequest<DemoStatus>('/demo')).enabled } catch { publicDemo.value = false } })
</script>

<template>
  <div class="site-shell">
    <header class="topbar">
      <RouterLink class="brand" to="/">
        <img class="brand-mark" src="/favicon.svg" alt="N/E" />
        <span>no-excuse</span>
      </RouterLink>
      <nav class="main-nav" :aria-label="t('nav.main')">
        <RouterLink v-if="auth.isAuthenticated" to="/dashboard">{{ t('nav.dashboard') }}</RouterLink>
        <RouterLink v-if="auth.isAuthenticated" to="/settings">{{ t('nav.settings') }}</RouterLink>
        <RouterLink v-if="!auth.isAuthenticated && !publicDemo" class="button button-small button-ghost" to="/login">{{ t('nav.recruiter') }}</RouterLink>
        <a class="github-mark" href="https://github.com/Axxon/no-excuse" target="_blank" rel="noopener noreferrer" aria-label="GitHub — no-excuse">
          <svg aria-hidden="true" viewBox="0 0 24 24"><path fill="currentColor" d="M12 .7a11.5 11.5 0 0 0-3.6 22.4c.6.1.8-.2.8-.5v-2c-3.3.7-4-1.4-4-1.4-.5-1.4-1.3-1.8-1.3-1.8-1.1-.7.1-.7.1-.7 1.2.1 1.8 1.2 1.8 1.2 1 1.8 2.7 1.3 3.4 1 .1-.8.4-1.3.8-1.6-2.7-.3-5.5-1.3-5.5-5.7 0-1.3.5-2.3 1.2-3.1-.1-.3-.5-1.6.1-3.2 0 0 1-.3 3.2 1.2a11 11 0 0 1 5.8 0C14.2 5 15.2 5.3 15.2 5.3c.6 1.6.2 2.9.1 3.2.8.8 1.2 1.8 1.2 3.1 0 4.4-2.8 5.4-5.5 5.7.4.4.8 1.1.8 2.2v3.1c0 .3.2.6.8.5A11.5 11.5 0 0 0 12 .7Z"/></svg>
        </a>
      </nav>
    </header>
    <aside v-if="auth.user?.organization?.is_demo" class="demo-banner"><div><strong>{{ t('demo.banner') }}</strong><span>{{ t('demo.bannerLead') }}</span></div><div class="actions"><RouterLink class="button button-small button-ghost" to="/settings">{{ t('demo.viewSettings') }}</RouterLink></div></aside>
    <main>
      <RouterView />
    </main>
    <footer class="footer">
      <strong>no-excuse</strong>
      <span>{{ t('footer.promise') }}</span>
      <a href="https://github.com/Axxon/no-excuse" target="_blank" rel="noreferrer">GitHub</a>
      <RouterLink to="/about">À propos</RouterLink>
      <a href="https://ko-fi.com/axxon" target="_blank" rel="noopener noreferrer">{{ t('footer.support') }}</a>
    </footer>
  </div>
</template>
