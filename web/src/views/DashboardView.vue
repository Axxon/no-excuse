<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { apiRequest, type AiMeta, type Offer } from '../api'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n(); const auth = useAuthStore(); const router = useRouter()
const offers = ref<Offer[]>([]); const providers = ref<AiMeta['providers']>([]); const showForm = ref(false); const error = ref(''); const saving = ref(false)
const integration = ref<{ intakeUrl: string; key: string } | null>(null)
const form = reactive({
  title: '', company: '', location: '', description: '', criteria: '',
  rejection_message: 'Merci pour votre candidature. Votre profil ne correspond pas suffisamment au périmètre professionnel défini pour cette offre.',
  final_rejection_message: 'Merci pour la qualité de votre candidature. Nous avons retenu un autre profil pour cette campagne et souhaitions vous transmettre une décision explicite.',
  screening_provider: '', screening_model: '', scoring_provider: '', scoring_model: '',
})

async function load(): Promise<void> {
  const [offerPayload, meta] = await Promise.all([apiRequest<{ data: Offer[] }>('/offers', {}, auth.token), apiRequest<AiMeta>('/meta/ai-providers', {}, auth.token)])
  offers.value = offerPayload.data; providers.value = meta.providers
  if (!form.company) form.company = auth.user?.organization?.name ?? ''
}
onMounted(async () => { try { await auth.loadUser(); await load() } catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') } })
async function createOffer(): Promise<void> {
  saving.value = true; error.value = ''
  const payload: Record<string, string | string[] | null> = {
    title: form.title, company: form.company, location: form.location || null, description: form.description,
    criteria: form.criteria.split(',').map(value => value.trim()).filter(Boolean),
    rejection_message: form.rejection_message, final_rejection_message: form.final_rejection_message,
  }
  if (form.screening_provider) { payload.screening_provider = form.screening_provider; payload.screening_model = form.screening_model || null }
  if (form.scoring_provider) { payload.scoring_provider = form.scoring_provider; payload.scoring_model = form.scoring_model || null }
  try {
    const created = await apiRequest<{ data: Offer; meta: { ingestion_key: string } }>('/offers', { method: 'POST', body: JSON.stringify(payload) }, auth.token)
    integration.value = { intakeUrl: created.data.intake_url ?? '', key: created.meta.ingestion_key }
    showForm.value = false; Object.assign(form, { title: '', location: '', description: '', criteria: '' }); await load()
  } catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
  finally { saving.value = false }
}
async function logout(): Promise<void> {
  try {
    if (auth.user?.organization?.is_demo) {
      if (!window.confirm(t('demo.releaseConfirm'))) return
      await auth.releaseDemo()
    } else await auth.logout()
    await router.push('/')
  } catch { window.alert(t('demo.releaseError')) }
}
</script>

<template>
  <section class="dashboard-header page-section">
    <div><span class="eyebrow">{{ t('dashboard.welcome', { name: auth.user?.name ?? '' }) }}</span><h1>{{ t('dashboard.title') }}</h1><p class="lead">{{ t('dashboard.lead') }}</p></div>
    <div class="actions"><button v-if="!auth.user?.organization?.is_demo" class="button" @click="showForm = !showForm">+ {{ t('dashboard.newOffer') }}</button><button class="text-button" @click="logout">{{ auth.user?.organization?.is_demo ? t('demo.release') : t('nav.logout') }}</button></div>
  </section>

  <section v-if="showForm" class="page-section creation-panel">
    <form class="form-card wide-form" @submit.prevent="createOffer">
      <div class="section-title"><div><span class="step-kicker">3 / 3</span><h2>{{ t('dashboard.newOffer') }}</h2></div><button type="button" class="text-button" @click="showForm = false">{{ t('common.cancel') }}</button></div>
      <p v-if="error" class="alert">{{ error }}</p>
      <label>{{ t('dashboard.titleField') }}<input v-model="form.title" autofocus required /></label>
      <div class="form-grid"><label>{{ t('dashboard.company') }}<input v-model="form.company" required /></label><label>{{ t('dashboard.location') }}<input v-model="form.location" /></label></div>
      <label>{{ t('dashboard.description') }}<textarea v-model="form.description" minlength="50" rows="5" required /></label>
      <label>{{ t('dashboard.criteria') }}<input v-model="form.criteria" :placeholder="t('dashboard.criteriaExample')" required /></label>
      <details><summary>{{ t('dashboard.advanced') }}</summary><div class="details-body"><div class="form-grid"><label>{{ t('dashboard.screeningProvider') }}<select v-model="form.screening_provider"><option value="">{{ t('dashboard.companyDefault') }}</option><option v-for="provider in providers" :key="provider.key" :value="provider.key">{{ provider.label }}</option></select></label><label>{{ t('dashboard.scoringProvider') }}<select v-model="form.scoring_provider"><option value="">{{ t('dashboard.companyDefault') }}</option><option v-for="provider in providers" :key="provider.key" :value="provider.key">{{ provider.label }}</option></select></label></div><label>{{ t('dashboard.rejection') }}<textarea v-model="form.rejection_message" rows="3" required /></label><label>{{ t('dashboard.finalRejection') }}<textarea v-model="form.final_rejection_message" rows="3" required /></label></div></details>
      <button class="button" :disabled="saving">{{ saving ? t('common.loading') : t('dashboard.create') }}</button>
    </form>
  </section>

  <section v-if="integration" class="page-section"><article class="integration-card"><div><span class="eyebrow">{{ t('dashboard.integrationCreated') }}</span><h2>{{ t('dashboard.integrationWarning') }}</h2></div><label>{{ t('dashboard.endpoint') }}<code>{{ integration.intakeUrl }}</code></label><label>{{ t('dashboard.secret') }}<code>{{ integration.key }}</code></label></article></section>

  <section class="page-section campaign-grid">
    <p v-if="error && !showForm" class="alert">{{ error }}</p>
    <div v-if="offers.length === 0" class="empty-state"><span>01</span><h2>{{ t('dashboard.empty') }}</h2><button class="button" @click="showForm = true">{{ t('dashboard.firstOffer') }}</button></div>
    <RouterLink v-for="offer in offers" :key="offer.uuid" class="campaign-card" :to="`/dashboard/offers/${offer.uuid}`">
      <div class="campaign-card-top"><span class="status" :class="`status-${offer.status}`">{{ t(`status.${offer.status}`) }}</span><span>→</span></div><h2>{{ offer.title }}</h2><p>{{ offer.company }}<template v-if="offer.location"> · {{ offer.location }}</template></p>
      <div class="campaign-stats"><strong>{{ offer.applications_count ?? 0 }}</strong><span>{{ t('dashboard.applications', { count: offer.applications_count ?? 0 }) }}</span><strong>{{ offer.pending_count ?? 0 }}</strong><span>{{ t('campaign.processing') }}</span></div>
    </RouterLink>
  </section>
</template>
