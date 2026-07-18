<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import { apiRequest, type DemoStatus } from '../api'

const { t } = useI18n(); const router = useRouter(); const auth = useAuthStore()
const email = ref(''); const password = ref(''); const error = ref(''); const loading = ref(false)
const publicDemo = ref<boolean | null>(null)
onMounted(async () => { try { publicDemo.value = (await apiRequest<DemoStatus>('/demo')).enabled } catch { publicDemo.value = false } })
async function launchDemo(): Promise<void> {
  loading.value = true; error.value = ''
  try { const offerUuid = await auth.startDemo(); await router.push(`/dashboard/offers/${offerUuid}`) }
  catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
  finally { loading.value = false }
}
async function submit(): Promise<void> {
  loading.value = true; error.value = ''
  try { await auth.login(email.value, password.value); await router.push('/dashboard') }
  catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
  finally { loading.value = false }
}
</script>

<template>
  <section class="auth-page page-section">
    <div class="auth-promise"><span class="eyebrow">{{ t('auth.title') }}</span><h1>{{ t('auth.lead') }}</h1><div class="quote-mark">“</div><p>{{ t('auth.promise') }}</p></div>
    <div class="auth-form-stack">
      <aside v-if="publicDemo" class="form-card demo-login-notice"><span class="step-kicker">{{ t('auth.publicDemoTitle') }}</span><h2>{{ t('auth.demoAccessTitle') }}</h2><p>{{ t('auth.publicDemoText') }}</p><p v-if="error" class="alert">{{ error }}</p><button class="button" :disabled="loading" @click="launchDemo">{{ loading ? t('home.demoStarting') : t('home.demoCta') }}</button><small>{{ t('auth.demoBackofficeHelp') }}</small></aside>
      <form v-else-if="publicDemo === false" class="form-card auth-form" @submit.prevent="submit">
      <span class="step-kicker">{{ t('auth.teamOnly') }}</span><h2>{{ t('auth.login') }}</h2><p v-if="error" class="alert">{{ error }}</p>
      <label>{{ t('auth.email') }}<input v-model="email" type="email" autocomplete="email" required /></label>
      <label>{{ t('auth.password') }}<input v-model="password" type="password" autocomplete="current-password" required /></label>
      <button class="button" :disabled="loading">{{ loading ? t('common.loading') : t('auth.login') }}</button>
      <small>{{ t('auth.installedInstanceHelp') }}</small>
      </form>
      <p v-else>{{ t('common.loading') }}</p>
    </div>
  </section>
</template>
