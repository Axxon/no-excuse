<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { API_URL, apiRequest, type CandidateApplication, type Offer } from '../api'
import { useAuthStore } from '../stores/auth'
import CvViewer from '../components/CvViewer.vue'
import MailPreview from '../components/MailPreview.vue'

const route = useRoute(); const { t } = useI18n(); const auth = useAuthStore(); const uuid = String(route.params.uuid)
const offer = ref<Offer | null>(null); const applications = ref<CandidateApplication[]>([]); const error = ref(''); const closingDate = ref(''); const busy = ref(false)
const feedbackDrafts = reactive<Record<string, string>>({}); const feedbackSavedUuid = ref('')
const ingestionKey = ref('')
const cvViewer = ref<{ blob: Blob; name: string; text: string | null } | null>(null)
const mailPreview = ref<{ html: string; candidate: string } | null>(null)
const pending = computed(() => applications.value.filter(item => ['received', 'screening', 'qualified', 'scoring'].includes(item.status)).length)
let poller: number | undefined
async function load(): Promise<void> {
  const [offerPayload, applicationPayload] = await Promise.all([apiRequest<{ data: Offer }>(`/offers/${uuid}`, {}, auth.token), apiRequest<{ data: CandidateApplication[] }>(`/offers/${uuid}/applications`, {}, auth.token)])
  applicationPayload.data.forEach((application) => {
    if (!(application.uuid in feedbackDrafts)) feedbackDrafts[application.uuid] = application.candidate_feedback ?? ''
  })
  offer.value = offerPayload.data; applications.value = applicationPayload.data
}
onMounted(async () => {
  try { await load() } catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
  if (auth.user?.organization?.is_demo) poller = window.setInterval(() => void load(), 2000)
})
onUnmounted(() => { if (poller) window.clearInterval(poller) })
async function action(path: string, body?: object): Promise<void> { busy.value = true; error.value = ''; try { await apiRequest(path, { method: 'POST', body: body ? JSON.stringify(body) : undefined }, auth.token); await load() } catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') } finally { busy.value = false } }
async function openCampaign(): Promise<void> { await action(`/offers/${uuid}/open`, { closes_at: new Date(closingDate.value).toISOString() }) }
async function closeCampaign(): Promise<void> { await action(`/offers/${uuid}/close`) }
async function openCv(application: CandidateApplication): Promise<void> {
  const response = await fetch(`${API_URL}/applications/${application.uuid}/cv`, { headers: { Authorization: `Bearer ${auth.token}` } })
  if (!response.ok) { error.value = response.status === 410 ? t('campaign.cvDeleted') : t('common.error'); return }
  const blob = await response.blob(); const name = application.cv_original_name ?? 'CV'; const isPdf = blob.type === 'application/pdf' || name.toLowerCase().endsWith('.pdf')
  cvViewer.value = { blob, name, text: isPdf ? null : await blob.text() }
  await load()
}
async function addNote(application: CandidateApplication): Promise<void> {
  const body = window.prompt(t('campaign.addNote')); if (!body) return
  await apiRequest(`/applications/${application.uuid}/annotations`, { method: 'POST', body: JSON.stringify({ body }) }, auth.token); await load()
}
async function previewDecision(application: CandidateApplication): Promise<void> {
  const response = await fetch(`${API_URL}/applications/${application.uuid}/decision-preview`, { headers: { Authorization: `Bearer ${auth.token}`, Accept: 'text/html' } })
  if (!response.ok) { error.value = t('common.error'); return }
  mailPreview.value = { html: await response.text(), candidate: application.candidate_name }
}
async function saveFeedback(application: CandidateApplication): Promise<void> {
  const response = await apiRequest<{ data: CandidateApplication }>(`/applications/${application.uuid}/feedback`, { method: 'PUT', body: JSON.stringify({ candidate_feedback: feedbackDrafts[application.uuid] }) }, auth.token)
  feedbackDrafts[application.uuid] = response.data.candidate_feedback ?? ''; feedbackSavedUuid.value = application.uuid
  window.setTimeout(() => { if (feedbackSavedUuid.value === application.uuid) feedbackSavedUuid.value = '' }, 2500)
}
async function move(application: CandidateApplication, offset: number): Promise<void> {
  const top = applications.value.filter(item => item.status === 'shortlisted'); const index = top.findIndex(item => item.uuid === application.uuid); const target = index + offset; if (index < 0 || target < 0 || target >= top.length) return
  const temporary = top[index]; top[index] = top[target]; top[target] = temporary
  await apiRequest(`/offers/${uuid}/applications/reorder`, { method: 'PUT', body: JSON.stringify({ applications: top.map(item => item.uuid) }) }, auth.token); await load()
}
async function select(application: CandidateApplication): Promise<void> { if (window.confirm(t('campaign.confirmSelect'))) await action(`/applications/${application.uuid}/select`) }
async function rotateIngestionKey(): Promise<void> {
  if (!window.confirm(t('campaign.rotateWarning'))) return
  const response = await apiRequest<{ ingestion_key: string }>(`/offers/${uuid}/ingestion-key`, { method: 'POST' }, auth.token)
  ingestionKey.value = response.ingestion_key
}
</script>

<template>
  <section v-if="offer" class="page-section campaign-heading">
    <div><span class="status" :class="`status-${offer.status}`">{{ t(`status.${offer.status}`) }}</span><h1>{{ offer.title }}</h1><p>{{ offer.company }} · {{ offer.location }}</p></div>
    <div v-if="offer.status === 'draft'" class="inline-form"><input v-model="closingDate" type="datetime-local" /><button class="button button-small" :disabled="busy || !closingDate" @click="openCampaign">{{ t('campaign.open') }}</button></div>
    <button v-if="offer.status === 'open'" class="button button-small" :disabled="busy || pending > 0" @click="closeCampaign">{{ pending > 0 ? t('demo.processingCount', { count: pending }) : t('campaign.close') }}</button>
  </section>
  <section v-if="offer && !auth.user?.organization?.is_demo" class="page-section integration-card">
    <div><span class="eyebrow">{{ t('campaign.integration') }}</span><h2>{{ t('campaign.integrationLead') }}</h2></div>
    <label>{{ t('dashboard.endpoint') }}<code>{{ offer.intake_url }}</code></label>
    <label v-if="ingestionKey">{{ t('dashboard.secret') }}<code>{{ ingestionKey }}</code><small>{{ t('dashboard.integrationWarning') }}</small></label>
    <button class="button button-small button-ghost" :disabled="busy" @click="rotateIngestionKey">{{ t('campaign.rotateKey') }}</button>
  </section>
  <section v-if="offer && auth.user?.organization?.is_demo" class="page-section demo-guide"><div><span class="eyebrow">{{ t('demo.fictional') }}</span><h2>{{ pending > 0 ? t('demo.analysisRunning') : t('demo.analysisReady') }}</h2><p>{{ t('demo.guide') }}</p></div><strong>{{ applications.length - pending }} / {{ applications.length }}</strong></section>
  <section class="page-section"><p v-if="error" class="alert">{{ error }}</p><p v-if="!offer">{{ t('common.loading') }}</p><p v-else-if="applications.length === 0">{{ t('campaign.empty') }}</p>
    <div class="application-list">
      <article v-for="application in applications" :key="application.uuid" class="application-card" :class="{ shortlisted: application.status === 'shortlisted', unread: !application.read_at }">
        <div class="rank"><strong>{{ application.recruiter_rank ?? '—' }}</strong><span v-if="!application.read_at">{{ t('campaign.unread') }}</span></div>
        <div class="candidate-main"><div class="candidate-title"><div><h2>{{ application.candidate_name }}</h2><p>{{ application.candidate_email }}<template v-if="application.source"> · {{ t('campaign.source') }} : {{ application.source }}</template></p></div><div class="candidate-statuses"><span v-if="application.notification_status" class="mail-status" :class="`mail-status-${application.notification_status}`">{{ t(`campaign.mail.${application.notification_status}`) }}</span><span class="status" :class="`status-${application.status}`">{{ t(`status.${application.status}`) }}</span></div></div>
          <p v-if="application.ai_summary" class="summary">{{ application.ai_summary }}</p><div v-if="application.score_breakdown" class="score-bars"><div v-for="(value, key) in application.score_breakdown" :key="key"><span>{{ key }}</span><progress :value="value" max="100" /><strong>{{ value }}</strong></div></div>
          <div v-if="application.annotations.length" class="notes"><p v-for="note in application.annotations" :key="note.uuid">{{ note.body }}</p></div>
          <template v-if="application.status === 'shortlisted'"><label class="feedback-field">{{ t('campaign.feedback') }}<textarea v-model="feedbackDrafts[application.uuid]" rows="2" /></label><p v-if="feedbackSavedUuid === application.uuid" class="feedback-saved">{{ t('campaign.feedbackSaved') }}</p></template>
          <div class="actions"><button class="button button-small button-ghost" :disabled="!application.cv_available" @click="openCv(application)">{{ application.cv_available ? t('campaign.viewCv') : t('campaign.cvDeleted') }}</button><button v-if="auth.user?.organization?.is_demo && application.notified_at && ['rejected_out_of_scope', 'rejected_final', 'selected'].includes(application.status)" class="button button-small button-ghost" @click="previewDecision(application)">{{ t('demo.previewMail') }}</button><button class="button button-small button-ghost" @click="addNote(application)">{{ t('campaign.addNote') }}</button><template v-if="application.status === 'shortlisted'"><button class="button button-small button-ghost" @click="saveFeedback(application)">{{ t('campaign.saveFeedback') }}</button><button class="icon-button" :aria-label="t('campaign.moveUp')" @click="move(application, -1)">↑</button><button class="icon-button" :aria-label="t('campaign.moveDown')" @click="move(application, 1)">↓</button><button class="button button-small" @click="select(application)">{{ t('campaign.select') }}</button></template></div>
        </div>
        <div class="score"><strong>{{ application.final_score?.toFixed(1) ?? '—' }}</strong><span>{{ t('campaign.score') }}</span></div>
      </article>
    </div>
  </section>
  <CvViewer v-if="cvViewer" v-bind="cvViewer" @close="cvViewer = null" />
  <MailPreview v-if="mailPreview" v-bind="mailPreview" @close="mailPreview = null" />
</template>
