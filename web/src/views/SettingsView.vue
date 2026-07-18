<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { apiRequest, type AiMeta, type Organization, type TeamMember } from '../api'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n(); const auth = useAuthStore(); const organization = ref<Organization | null>(null); const members = ref<TeamMember[]>([]); const providers = ref<AiMeta['providers']>([]); const aiMode = ref('demo')
const saved = ref(false); const error = ref(''); const inviteOpen = ref(false)
const member = reactive({ name: '', email: '', role: 'recruiter', password: '' })
async function load(): Promise<void> {
  const [org, team, meta] = await Promise.all([apiRequest<{ data: Organization }>('/organization', {}, auth.token), apiRequest<{ data: TeamMember[] }>('/organization/members', {}, auth.token), apiRequest<AiMeta>('/meta/ai-providers', {}, auth.token)])
  organization.value = org.data; members.value = team.data; providers.value = meta.providers; aiMode.value = meta.mode
}
onMounted(async () => { try { await load() } catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') } })
async function save(): Promise<void> {
  if (!organization.value) return
  try { const response = await apiRequest<{ data: Organization }>('/organization', { method: 'PUT', body: JSON.stringify(organization.value) }, auth.token); organization.value = response.data; saved.value = true; window.setTimeout(() => saved.value = false, 2500) }
  catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
}
async function addMember(): Promise<void> {
  try { await apiRequest('/organization/members', { method: 'POST', body: JSON.stringify(member) }, auth.token); inviteOpen.value = false; Object.assign(member, { name: '', email: '', role: 'recruiter', password: '' }); await load() }
  catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
}
</script>

<template>
  <section class="page-section page-heading compact-heading"><span class="eyebrow">{{ t('settings.eyebrow') }}</span><h1>{{ t('settings.title') }}</h1><p>{{ t('settings.lead') }}</p></section>
  <section v-if="organization" class="page-section settings-layout">
    <form class="form-card" @submit.prevent="save">
      <div class="section-title"><span class="step-kicker">2 / 3</span><h2>{{ t('settings.company') }}</h2></div><p v-if="error" class="alert">{{ error }}</p><p v-if="saved" class="success-line">{{ t('settings.saved') }}</p>
      <label>{{ t('setup.company') }}<input v-model="organization.name" required /></label>
      <div class="form-grid"><label>{{ t('settings.sender') }}<input v-model="organization.notification_sender_name" required /></label><label>{{ t('settings.replyTo') }}<input v-model="organization.notification_reply_to" type="email" required /></label></div>
      <div class="form-grid"><label>{{ t('settings.filterProvider') }}<select v-model="organization.default_screening_provider"><option v-for="provider in providers" :key="provider.key" :value="provider.key">{{ provider.label }}</option></select></label><label>{{ t('settings.scoreProvider') }}<select v-model="organization.default_scoring_provider"><option v-for="provider in providers" :key="provider.key" :value="provider.key">{{ provider.label }}</option></select></label></div>
      <div class="provider-security"><strong>{{ aiMode === 'demo' ? t('settings.demoMode') : t('settings.liveMode') }}</strong><p>{{ t('settings.secretHelp') }}</p><div class="provider-statuses"><span v-for="provider in providers" :key="provider.key" :class="{ missing: aiMode === 'live' && !provider.credential_configured }"><i aria-hidden="true" />{{ provider.label }} · {{ provider.credential_configured ? t('settings.configured') : t('settings.missing') }}</span></div></div>
      <div class="velocity-card"><div><strong>{{ t('settings.velocity') }}</strong><small>{{ t('settings.velocityLead') }}</small></div><label>{{ t('settings.filterWorkers') }}<input v-model.number="organization.screening_workers" type="number" min="1" max="10" /></label><label>{{ t('settings.scoreWorkers') }}<input v-model.number="organization.scoring_workers" type="number" min="1" max="10" /></label></div>
      <details><summary>{{ t('settings.models') }}</summary><div class="form-grid details-body"><label>{{ t('settings.filterModel') }}<input v-model="organization.default_screening_model" /></label><label>{{ t('settings.scoreModel') }}<input v-model="organization.default_scoring_model" /></label></div></details>
      <details><summary>{{ t('settings.prompts') }}</summary><div class="details-body prompt-fields"><label>{{ t('settings.filterPrompt') }}<textarea v-model="organization.screening_prompt" rows="7" minlength="40" required /><small>{{ t('settings.filterPromptHelp') }}</small></label><label>{{ t('settings.scorePrompt') }}<textarea v-model="organization.scoring_prompt" rows="7" minlength="40" required /><small>{{ t('settings.scorePromptHelp') }}</small></label></div></details>
      <button class="button">{{ t('common.save') }}</button>
    </form>
    <div class="form-card"><div class="section-title"><h2>{{ t('settings.team') }}</h2><button v-if="auth.user?.role === 'owner' || auth.user?.role === 'admin'" class="button button-small button-ghost" @click="inviteOpen = !inviteOpen">+ {{ t('settings.add') }}</button></div><p>{{ t('settings.teamLead') }}</p>
      <form v-if="inviteOpen" class="member-form" @submit.prevent="addMember"><label>{{ t('auth.name') }}<input v-model="member.name" required /></label><label>{{ t('auth.email') }}<input v-model="member.email" type="email" required /></label><label>{{ t('settings.role') }}<select v-model="member.role"><option value="admin">{{ t('settings.admin') }}</option><option value="recruiter">{{ t('settings.recruiter') }}</option><option value="viewer">{{ t('settings.viewer') }}</option></select></label><label>{{ t('settings.temporaryPassword') }}<input v-model="member.password" type="password" minlength="10" required /></label><button class="button button-small">{{ t('settings.createAccess') }}</button></form>
      <div class="member-list"><article v-for="item in members" :key="item.uuid"><span class="avatar">{{ item.name.slice(0, 1) }}</span><div><strong>{{ item.name }}</strong><small>{{ item.email }}</small></div><span class="role-pill">{{ t(`settings.${item.role}`) }}</span></article></div>
    </div>
  </section>
</template>
