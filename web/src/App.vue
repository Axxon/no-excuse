<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from './stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
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
        <RouterLink v-if="auth.isAuthenticated" to="/settings">{{ t('nav.settings') }}</RouterLink>
        <RouterLink v-else class="button button-small button-ghost" to="/login">{{ t('nav.recruiter') }}</RouterLink>
      </nav>
    </header>
    <main>
      <RouterView />
    </main>
    <footer class="footer">
      <strong>no-excuse</strong>
      <span>{{ t('footer.promise') }}</span>
      <a href="https://github.com/Axxon/no-excuse" target="_blank" rel="noreferrer">GitHub</a>
    </footer>
  </div>
</template>
