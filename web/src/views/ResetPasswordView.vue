<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { apiRequest, type AuthPayload } from '../api'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n(); const route = useRoute(); const router = useRouter(); const auth = useAuthStore()
const email = ref(typeof route.query.email === 'string' ? route.query.email : ''); const token = ref(typeof route.query.token === 'string' ? route.query.token : '')
const password = ref(''); const confirmation = ref(''); const error = ref(''); const loading = ref(false)
async function submit(): Promise<void> { if (password.value !== confirmation.value) { error.value = t('setup.passwordMismatch'); return } loading.value = true; error.value = ''; try { const payload = await apiRequest<AuthPayload>('/auth/reset-password', { method: 'POST', body: JSON.stringify({ email: email.value, token: token.value, password: password.value, password_confirmation: confirmation.value }) }); auth.accept(payload); await router.push('/dashboard') } catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') } finally { loading.value = false } }
</script>
<template><section class="auth-page page-section"><div class="auth-promise"><span class="eyebrow">{{ t('auth.reset') }}</span><h1>{{ t('auth.choosePassword') }}</h1></div><form class="form-card auth-form" @submit.prevent="submit"><p v-if="error" class="alert">{{ error }}</p><label>{{ t('auth.email') }}<input v-model="email" type="email" readonly /></label><label>{{ t('auth.password') }}<input v-model="password" type="password" minlength="12" required /></label><label>{{ t('auth.confirmation') }}<input v-model="confirmation" type="password" minlength="12" required /></label><button class="button" :disabled="loading || !token">{{ loading ? t('common.loading') : t('auth.reset') }}</button></form></section></template>
