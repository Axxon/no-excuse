<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { apiRequest, type AiMeta, type Offer } from '../api'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n(); const auth = useAuthStore()
const offers = ref<Offer[]>([]); const providers = ref<AiMeta['providers']>([]); const showForm = ref(false); const error = ref(''); const saving = ref(false)
const integration = ref<{ intakeUrl: string; key: string } | null>(null)
const form = reactive({ title: '', company: '', location: '', description: '', criteria: '', rejection_message: 'Merci pour votre candidature. Votre profil ne correspond pas suffisamment au périmètre professionnel défini pour cette offre.', final_rejection_message: 'Merci pour la qualité de votre candidature. Nous avons retenu un autre profil pour cette campagne et souhaitions vous transmettre une décision explicite.', screening_provider: 'openai', screening_model: '', scoring_provider: 'anthropic', scoring_model: '' })

async function load(): Promise<void> {
  const [offerPayload, meta] = await Promise.all([apiRequest<{ data: Offer[] }>('/offers', {}, auth.token), apiRequest<AiMeta>('/meta/ai-providers')])
  offers.value = offerPayload.data; providers.value = meta.providers
}
onMounted(async () => { try { await load() } catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') } })
async function createOffer(): Promise<void> {
  saving.value = true; error.value = ''
  try {
    const created = await apiRequest<{ data: Offer; meta: { ingestion_key: string } }>('/offers', { method: 'POST', body: JSON.stringify({ ...form, criteria: form.criteria.split(',').map(value => value.trim()).filter(Boolean), screening_model: form.screening_model || null, scoring_model: form.scoring_model || null }) }, auth.token)
    integration.value = { intakeUrl: created.data.intake_url ?? '', key: created.meta.ingestion_key }
    showForm.value = false; await load()
  } catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
  finally { saving.value = false }
}
</script>

<template>
  <section class="dashboard-header page-section">
    <div><span class="eyebrow">{{ t('dashboard.welcome', { name: auth.user?.name ?? '' }) }}</span><h1>{{ t('dashboard.title') }}</h1></div>
    <div class="actions"><button class="button" @click="showForm = !showForm">+ {{ t('dashboard.newOffer') }}</button><button class="text-button" @click="auth.logout()">{{ t('nav.logout') }}</button></div>
  </section>
  <section v-if="integration" class="page-section">
    <article class="integration-card">
      <div><span class="eyebrow">{{ t('dashboard.integrationCreated') }}</span><h2>{{ t('dashboard.integrationWarning') }}</h2></div>
      <label>{{ t('dashboard.endpoint') }}<code>{{ integration.intakeUrl }}</code></label>
      <label>{{ t('dashboard.secret') }}<code>{{ integration.key }}</code></label>
    </article>
  </section>
  <section v-if="showForm" class="page-section">
    <form class="form-card wide-form" @submit.prevent="createOffer">
      <h2>{{ t('dashboard.newOffer') }}</h2><p v-if="error" class="alert">{{ error }}</p>
      <div class="form-grid"><label>{{ t('dashboard.titleField') }}<input v-model="form.title" required /></label><label>{{ t('dashboard.company') }}<input v-model="form.company" required /></label><label>{{ t('dashboard.location') }}<input v-model="form.location" /></label><label>{{ t('dashboard.criteria') }}<input v-model="form.criteria" required /></label></div>
      <label>{{ t('dashboard.description') }}<textarea v-model="form.description" minlength="50" rows="5" required /></label>
      <div class="form-grid"><label>{{ t('dashboard.screeningProvider') }}<select v-model="form.screening_provider"><option v-for="provider in providers" :key="provider.key" :value="provider.key">{{ provider.label }}</option></select></label><label>{{ t('dashboard.screeningModel') }}<input v-model="form.screening_model" :placeholder="providers.find(item => item.key === form.screening_provider)?.defaults.screening" /></label><label>{{ t('dashboard.scoringProvider') }}<select v-model="form.scoring_provider"><option v-for="provider in providers" :key="provider.key" :value="provider.key">{{ provider.label }}</option></select></label><label>{{ t('dashboard.scoringModel') }}<input v-model="form.scoring_model" :placeholder="providers.find(item => item.key === form.scoring_provider)?.defaults.scoring" /></label></div>
      <label>{{ t('dashboard.rejection') }}<textarea v-model="form.rejection_message" rows="3" required /></label><label>{{ t('dashboard.finalRejection') }}<textarea v-model="form.final_rejection_message" rows="3" required /></label>
      <button class="button" :disabled="saving">{{ saving ? t('common.loading') : t('dashboard.create') }}</button>
    </form>
  </section>
  <section class="page-section campaign-grid">
    <p v-if="error && !showForm" class="alert">{{ error }}</p><p v-if="offers.length === 0">{{ t('dashboard.empty') }}</p>
    <RouterLink v-for="offer in offers" :key="offer.uuid" class="campaign-card" :to="`/dashboard/offers/${offer.uuid}`">
      <div class="campaign-card-top"><span class="status" :class="`status-${offer.status}`">{{ t(`status.${offer.status}`) }}</span><span>→</span></div>
      <h2>{{ offer.title }}</h2><p>{{ offer.company }} · {{ offer.location }}</p>
      <div class="campaign-stats"><strong>{{ offer.applications_count ?? 0 }}</strong><span>{{ t('dashboard.applications', { count: offer.applications_count ?? 0 }) }}</span><strong>{{ offer.pending_count ?? 0 }}</strong><span>{{ t('campaign.processing') }}</span></div>
    </RouterLink>
  </section>
</template>
