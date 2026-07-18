<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n(); const router = useRouter(); const auth = useAuthStore()
const company = ref(''); const name = ref(''); const email = ref(''); const password = ref(''); const confirmation = ref('')
const error = ref(''); const loading = ref(false)
async function submit(): Promise<void> {
  if (password.value !== confirmation.value) { error.value = t('setup.passwordMismatch'); return }
  loading.value = true; error.value = ''
  try { await auth.setup(company.value, name.value, email.value, password.value); await router.push('/settings') }
  catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
  finally { loading.value = false }
}
</script>

<template>
  <section class="setup-page page-section">
    <div class="setup-intro"><span class="eyebrow">{{ t('setup.eyebrow') }}</span><h1>{{ t('setup.title') }}</h1><p class="lead">{{ t('setup.lead') }}</p><ol class="simple-steps"><li class="active">{{ t('setup.step1') }}</li><li>{{ t('setup.step2') }}</li><li>{{ t('setup.step3') }}</li></ol></div>
    <form class="form-card auth-form" @submit.prevent="submit">
      <span class="step-kicker">1 / 3</span><h2>{{ t('setup.companyAndOwner') }}</h2><p v-if="error" class="alert">{{ error }}</p>
      <label>{{ t('setup.company') }}<input v-model="company" autofocus required /></label>
      <label>{{ t('setup.ownerName') }}<input v-model="name" autocomplete="name" required /></label>
      <label>{{ t('auth.email') }}<input v-model="email" type="email" autocomplete="email" required /></label>
      <label>{{ t('auth.password') }}<input v-model="password" type="password" minlength="12" autocomplete="new-password" required /><small>{{ t('auth.passwordRules') }}</small></label>
      <label>{{ t('auth.confirmation') }}<input v-model="confirmation" type="password" autocomplete="new-password" required /></label>
      <button class="button" :disabled="loading">{{ loading ? t('common.loading') : t('setup.continue') }}</button>
    </form>
  </section>
</template>
