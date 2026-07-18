<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { apiRequest, type AiMeta, type OperationsStatus, type Organization, type TeamMember } from '../api'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n(); const auth = useAuthStore(); const organization = ref<Organization | null>(null); const members = ref<TeamMember[]>([]); const providers = ref<AiMeta['providers']>([]); const aiMode = ref('demo')
const saved = ref(false); const error = ref(''); const inviteOpen = ref(false)
const demoReadOnly = computed(() => Boolean(auth.user?.organization?.is_demo))
const settingsReadOnly = computed(() => demoReadOnly.value || !['owner', 'admin'].includes(auth.user?.role ?? ''))
const member = reactive({ name: '', email: '', role: 'recruiter' })
const mfaPassword = ref(''); const mfaSaved = ref(false)
const operations = ref<OperationsStatus | null>(null)
async function load(): Promise<void> {
  const operationsRequest = ['owner', 'admin'].includes(auth.user?.role ?? '') ? apiRequest<OperationsStatus>('/operations/status', {}, auth.token) : Promise.resolve(null)
  const [org, team, meta, health] = await Promise.all([apiRequest<{ data: Organization }>('/organization', {}, auth.token), apiRequest<{ data: TeamMember[] }>('/organization/members', {}, auth.token), apiRequest<AiMeta>('/meta/ai-providers', {}, auth.token), operationsRequest])
  organization.value = org.data; members.value = team.data; providers.value = meta.providers; aiMode.value = meta.mode
  operations.value = health
}
onMounted(async () => { try { await load() } catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') } })
async function save(): Promise<void> {
  if (!organization.value) return
  try { const response = await apiRequest<{ data: Organization }>('/organization', { method: 'PUT', body: JSON.stringify(organization.value) }, auth.token); organization.value = response.data; saved.value = true; window.setTimeout(() => saved.value = false, 2500) }
  catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
}
async function addMember(): Promise<void> {
  try { await apiRequest('/organization/members', { method: 'POST', body: JSON.stringify(member) }, auth.token); inviteOpen.value = false; Object.assign(member, { name: '', email: '', role: 'recruiter' }); await load() }
  catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
}
async function resendInvitation(item: TeamMember): Promise<void> {
  try { await apiRequest(`/organization/members/${item.uuid}/resend-invitation`, { method: 'POST' }, auth.token) }
  catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
}
async function removeMember(item: TeamMember): Promise<void> {
  if (!window.confirm(t('settings.confirmRemoveMember', { name: item.name }))) return
  try { await apiRequest(`/organization/members/${item.uuid}`, { method: 'DELETE' }, auth.token); await load() }
  catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
}
async function toggleMfa(): Promise<void> {
  if (!auth.user) return
  try { const response = await apiRequest<{ mfa_email_enabled: boolean }>('/auth/mfa', { method: 'PUT', body: JSON.stringify({ enabled: !auth.user.mfa_email_enabled, password: mfaPassword.value }) }, auth.token); auth.user.mfa_email_enabled = response.mfa_email_enabled; mfaPassword.value = ''; mfaSaved.value = true; window.setTimeout(() => mfaSaved.value = false, 2500) }
  catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
}
</script>

<template>
  <section class="page-section page-heading compact-heading"><span class="eyebrow">{{ t('settings.eyebrow') }}</span><h1>{{ t('settings.title') }}</h1><p>{{ t('settings.lead') }}</p></section>
  <section v-if="organization" class="page-section settings-layout">
    <form class="form-card" @submit.prevent="save">
      <div class="section-title"><span class="step-kicker">2 / 3</span><h2>{{ t('settings.company') }}</h2></div><p v-if="settingsReadOnly" class="locked-notice">🔒 {{ demoReadOnly ? t('settings.demoLocked') : t('settings.roleLocked') }}</p><p v-if="error" class="alert">{{ error }}</p><p v-if="saved" class="success-line">{{ t('settings.saved') }}</p>
      <fieldset class="settings-fields" :disabled="settingsReadOnly">
      <label>{{ t('setup.company') }}<input v-model="organization.name" required /></label>
      <div class="form-grid"><label>{{ t('settings.sender') }}<input v-model="organization.notification_sender_name" required /></label><label>{{ t('settings.replyTo') }}<input v-model="organization.notification_reply_to" type="email" required /></label></div>
      <div class="form-grid"><label>{{ t('settings.filterProvider') }}<select v-model="organization.default_screening_provider"><option v-for="provider in providers" :key="provider.key" :value="provider.key">{{ provider.label }}</option></select></label><label>{{ t('settings.scoreProvider') }}<select v-model="organization.default_scoring_provider"><option v-for="provider in providers" :key="provider.key" :value="provider.key">{{ provider.label }}</option></select></label></div>
      <div class="provider-security"><strong>{{ aiMode === 'demo' ? t('settings.demoMode') : t('settings.liveMode') }}</strong><p>{{ t('settings.secretHelp') }}</p><div class="provider-statuses"><span v-for="provider in providers" :key="provider.key" :class="{ missing: aiMode === 'live' && !provider.credential_configured }"><i aria-hidden="true" />{{ provider.label }} · {{ provider.credential_configured ? t('settings.configured') : t('settings.missing') }}</span></div></div>
      <div class="velocity-card"><div><strong>{{ t('settings.velocity') }}</strong><small>{{ t('settings.velocityLead') }}</small></div><label>{{ t('settings.filterWorkers') }}<input v-model.number="organization.screening_workers" type="number" min="1" max="10" /></label><label>{{ t('settings.scoreWorkers') }}<input v-model.number="organization.scoring_workers" type="number" min="1" max="10" /></label></div>
      <details><summary>{{ t('settings.models') }}</summary><div class="form-grid details-body"><label>{{ t('settings.filterModel') }}<input v-model="organization.default_screening_model" /></label><label>{{ t('settings.scoreModel') }}<input v-model="organization.default_scoring_model" /></label></div></details>
      <details><summary>{{ t('settings.prompts') }}</summary><div class="details-body prompt-fields"><label>{{ t('settings.filterPrompt') }}<textarea v-model="organization.screening_prompt" rows="7" minlength="40" required /><small>{{ t('settings.filterPromptHelp') }}</small></label><label>{{ t('settings.scorePrompt') }}<textarea v-model="organization.scoring_prompt" rows="7" minlength="40" required /><small>{{ t('settings.scorePromptHelp') }}</small></label></div></details>
      <details><summary>{{ t('settings.retention') }}</summary><div class="form-grid details-body"><label>{{ t('settings.rejectedCvDays') }}<input v-model.number="organization.rejected_cv_retention_days" type="number" min="0" max="730" /></label><label>{{ t('settings.selectedCvDays') }}<input v-model.number="organization.selected_cv_retention_days" type="number" min="1" max="730" /></label><label>{{ t('settings.candidateDataDays') }}<input v-model.number="organization.candidate_data_retention_days" type="number" min="30" max="1825" /></label></div></details>
      <button v-if="!settingsReadOnly" class="button">{{ t('common.save') }}</button>
      </fieldset>
    </form>
    <div class="form-card"><div class="section-title"><h2>{{ t('settings.team') }}</h2><button v-if="!demoReadOnly && (auth.user?.role === 'owner' || auth.user?.role === 'admin')" class="button button-small button-ghost" @click="inviteOpen = !inviteOpen">+ {{ t('settings.add') }}</button></div><p>{{ demoReadOnly ? t('settings.demoTeamLocked') : t('settings.teamLead') }}</p>
      <div v-if="operations" class="provider-security"><strong>{{ t('settings.operations') }}</strong><p>{{ t('settings.operationsLead') }}</p><div class="provider-statuses"><span>{{ t('settings.queueScreening') }} · {{ operations.queues.screening ?? '—' }}</span><span>{{ t('settings.queueScoring') }} · {{ operations.queues.scoring ?? '—' }}</span><span>{{ t('settings.queueMail') }} · {{ operations.queues.notifications ?? '—' }}</span><span :class="{ missing: operations.processing_failures > 0 }">{{ t('settings.processingFailures') }} · {{ operations.processing_failures }}</span><span :class="{ missing: operations.notification_failures > 0 }">{{ t('settings.mailFailures') }} · {{ operations.notification_failures }}</span><span :class="{ missing: operations.failed_jobs > 0 }">{{ t('settings.failedJobs') }} · {{ operations.failed_jobs }}</span></div></div>
      <div v-if="!demoReadOnly" class="provider-security"><strong>{{ t('settings.mfa') }}</strong><p>{{ auth.user?.mfa_email_enabled ? t('settings.mfaEnabled') : t('settings.mfaDisabled') }}</p><div class="inline-form"><input v-model="mfaPassword" type="password" :placeholder="t('auth.password')" /><button class="button button-small button-ghost" :disabled="!mfaPassword" @click="toggleMfa">{{ auth.user?.mfa_email_enabled ? t('settings.disableMfa') : t('settings.enableMfa') }}</button></div><small v-if="mfaSaved">{{ t('settings.saved') }}</small></div>
      <form v-if="inviteOpen" class="member-form" @submit.prevent="addMember"><label>{{ t('auth.name') }}<input v-model="member.name" required /></label><label>{{ t('auth.email') }}<input v-model="member.email" type="email" required /></label><label>{{ t('settings.role') }}<select v-model="member.role"><option value="admin">{{ t('settings.admin') }}</option><option value="recruiter">{{ t('settings.recruiter') }}</option><option value="viewer">{{ t('settings.viewer') }}</option></select></label><button class="button button-small">{{ t('settings.sendInvitation') }}</button></form>
      <div class="member-list"><article v-for="item in members" :key="item.uuid"><span class="avatar">{{ item.name.slice(0, 1) }}</span><div><strong>{{ item.name }}</strong><small>{{ item.email }}</small></div><button v-if="item.invitation_pending && !demoReadOnly && (auth.user?.role === 'owner' || auth.user?.role === 'admin')" class="button button-small button-ghost" @click="resendInvitation(item)">{{ t('settings.resendInvitation') }}</button><button v-if="!demoReadOnly && item.role !== 'owner' && item.uuid !== auth.user?.uuid && (auth.user?.role === 'owner' || auth.user?.role === 'admin')" class="button button-small button-ghost" @click="removeMember(item)">{{ t('settings.removeMember') }}</button><span class="role-pill">{{ t(`settings.${item.role}`) }}</span></article></div>
    </div>
  </section>
</template>
