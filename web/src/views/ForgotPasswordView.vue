<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { apiRequest } from '../api'

const { t } = useI18n(); const email = ref(''); const sent = ref(false); const error = ref(''); const loading = ref(false)
async function submit(): Promise<void> { loading.value = true; error.value = ''; try { await apiRequest('/auth/forgot-password', { method: 'POST', body: JSON.stringify({ email: email.value }) }); sent.value = true } catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') } finally { loading.value = false } }
</script>
<template><section class="auth-page page-section"><div class="auth-promise"><span class="eyebrow">{{ t('auth.reset') }}</span><h1>{{ t('auth.resetLead') }}</h1></div><form class="form-card auth-form" @submit.prevent="submit"><p v-if="error" class="alert">{{ error }}</p><p v-if="sent" class="success-line">{{ t('auth.resetSent') }}</p><label>{{ t('auth.email') }}<input v-model="email" type="email" autocomplete="email" required /></label><button class="button" :disabled="loading">{{ loading ? t('common.loading') : t('auth.sendReset') }}</button></form></section></template>
