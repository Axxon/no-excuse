<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n(); const router = useRouter(); const auth = useAuthStore()
const registerMode = ref(false); const name = ref(''); const email = ref(''); const password = ref(''); const error = ref(''); const loading = ref(false)
async function submit(): Promise<void> {
  loading.value = true; error.value = ''
  try { if (registerMode.value) await auth.register(name.value, email.value, password.value); else await auth.login(email.value, password.value); await router.push('/dashboard') }
  catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
  finally { loading.value = false }
}
</script>

<template>
  <section class="auth-page page-section">
    <div class="auth-promise"><span class="eyebrow">{{ t('auth.title') }}</span><h1>{{ t('auth.lead') }}</h1><div class="quote-mark">“</div><p>Une décision assistée, jamais automatisée.</p></div>
    <form class="form-card auth-form" @submit.prevent="submit">
      <h2>{{ registerMode ? t('auth.register') : t('auth.login') }}</h2><p v-if="error" class="alert">{{ error }}</p>
      <label v-if="registerMode">{{ t('auth.name') }}<input v-model="name" required /></label>
      <label>{{ t('auth.email') }}<input v-model="email" type="email" required /></label>
      <label>{{ t('auth.password') }}<input v-model="password" type="password" minlength="10" required /></label>
      <button class="button" :disabled="loading">{{ loading ? t('common.loading') : registerMode ? t('auth.register') : t('auth.login') }}</button>
      <button class="text-button" type="button" @click="registerMode = !registerMode">{{ registerMode ? t('auth.switchLogin') : t('auth.switchRegister') }}</button>
    </form>
  </section>
</template>
