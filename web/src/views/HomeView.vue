<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { apiRequest, type DemoStatus } from '../api'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n()
const router = useRouter(); const route = useRoute(); const auth = useAuthStore(); const demo = ref<DemoStatus | null>(null); const starting = ref(false); const error = ref(''); const waitlistEmail = ref(''); const waitlistSent = ref(false)
const accessToken = ref(typeof route.query.demo_access === 'string' ? route.query.demo_access : '')
const ownWaitlistReference = ref(sessionStorage.getItem('no-excuse-waitlist-reference') ?? '')
async function loadDemoStatus(): Promise<void> {
  try { demo.value = await apiRequest<DemoStatus>('/demo', { headers: { 'X-Demo-Visitor': localStorage.getItem('no-excuse-demo-visitor') ?? '' } }); localStorage.setItem('no-excuse-demo-visitor', demo.value.visitor_reference) } catch { demo.value = null }
}
onMounted(loadDemoStatus)
async function startDemo(): Promise<void> {
  starting.value = true; error.value = ''
  try { const offerUuid = await auth.startDemo(accessToken.value || undefined); await router.push(`/dashboard/offers/${offerUuid}`) }
  catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error'); await loadDemoStatus() }
  finally { starting.value = false }
}
async function joinWaitlist(): Promise<void> {
  error.value = ''
  try { const response = await apiRequest<{ reference: string }>('/demo/waitlist', { method: 'POST', body: JSON.stringify({ email: waitlistEmail.value, locale: 'fr' }) }); ownWaitlistReference.value = response.reference; sessionStorage.setItem('no-excuse-waitlist-reference', response.reference); waitlistSent.value = true; await loadDemoStatus() }
  catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
}
</script>

<template>
  <section class="hero page-section">
    <div class="hero-copy">
      <span class="eyebrow">{{ t('home.eyebrow') }}</span>
      <h1>{{ t('home.title') }}</h1>
      <p class="hero-lead">{{ t('home.lead') }}</p>
      <div class="actions">
        <button v-if="demo?.enabled" class="button" :disabled="starting || (demo.at_capacity && !accessToken)" @click="startDemo">{{ starting ? t('home.demoStarting') : (demo.at_capacity && !accessToken ? t('home.demoFull') : t('home.demoCta')) }}</button>
        <RouterLink v-if="demo && !demo.enabled" class="button button-ghost" to="/login">{{ t('home.recruiterCta') }}</RouterLink>
        <a class="button button-ghost" href="https://github.com/Axxon/no-excuse#démarrage" target="_blank" rel="noreferrer">{{ t('home.installCta') }}</a>
      </div>
      <p v-if="demo?.enabled" class="demo-promise">{{ t('home.demoPromise', { count: demo.candidate_count, hours: demo.lifetime_hours }) }}</p>
      <p v-if="demo?.enabled" class="demo-capacity">{{ demo.active_sessions > demo.max_sessions ? t('home.demoCapacityTransition', { count: demo.active_sessions, max: demo.max_sessions }) : t('home.demoActiveSessions', { count: demo.active_sessions, max: demo.max_sessions }) }}</p>
      <p v-if="error" class="alert">{{ error }}</p>
      <form v-if="demo?.enabled && demo.at_capacity && !waitlistSent" class="waitlist-form" @submit.prevent="joinWaitlist"><label>{{ t('home.waitlistLead') }}<input v-model="waitlistEmail" required type="email" :placeholder="t('home.waitlistPlaceholder')" /></label><button class="button button-small" type="submit">{{ t('home.waitlistCta') }}</button></form>
      <p v-if="waitlistSent" class="success-line">{{ t('home.waitlistSuccess') }}</p>
      <aside v-if="demo?.enabled && (demo.at_capacity || demo.waitlist_count > 0)" class="waitlist-queue" aria-live="polite">
        <div><strong>{{ t('home.waitlistQueueTitle') }}</strong><span>{{ t('home.waitlistQueueCount', { count: demo.waitlist_count }) }}</span></div>
        <ol><li v-for="entry in demo.waitlist" :key="entry.reference"><span>{{ entry.position }}</span><code>{{ entry.reference === ownWaitlistReference ? t('home.waitlistYou') : t('home.waitlistPrivate') }}</code></li></ol>
        <p v-if="demo.waitlist_count === 0" class="waitlist-empty">{{ t('home.waitlistQueueEmpty') }}</p>
        <small>{{ t('home.waitlistPrivacy') }}</small>
      </aside>
    </div>
    <div class="hero-visual" aria-hidden="true">
      <div class="signal-card signal-card-main"><span>94</span><small>match</small></div>
      <div class="signal-card signal-card-left"><span>10</span><small>top</small></div>
      <div class="signal-card signal-card-right"><span>✓</span><small>réponse</small></div>
      <div class="orbit"></div>
    </div>
  </section>

  <section class="access-paths page-section">
    <div class="section-title"><div><span class="eyebrow">{{ t('home.accessEyebrow') }}</span><h2>{{ t(demo?.enabled ? 'home.accessPublicTitle' : 'home.accessTitle') }}</h2></div></div>
    <div class="access-grid">
      <article><span>1</span><h3>{{ t('home.accessDemoTitle') }}</h3><p>{{ t('home.accessDemoText') }}</p><button v-if="demo?.enabled" class="text-button" :disabled="starting || (demo.at_capacity && !accessToken)" @click="startDemo">{{ demo.at_capacity && !accessToken ? t('home.demoFull') : t('home.demoCta') }} →</button></article>
      <article><span>2</span><h3>{{ t('home.accessInstallTitle') }}</h3><p>{{ t('home.accessInstallText') }}</p><a class="text-button" href="https://github.com/Axxon/no-excuse#démarrage" target="_blank" rel="noopener noreferrer">{{ t('home.installCta') }} →</a></article>
      <article v-if="demo && !demo.enabled"><span>3</span><h3>{{ t('home.accessLoginTitle') }}</h3><p>{{ t('home.accessLoginText') }}</p><RouterLink class="text-button" to="/login">{{ t('home.loginCta') }} →</RouterLink></article>
    </div>
  </section>

  <section class="metrics page-section">
    <article><strong>{{ t('home.metricOne') }}</strong><p>{{ t('home.metricOneText') }}</p></article>
    <article><strong>{{ t('home.metricTwo') }}</strong><p>{{ t('home.metricTwoText') }}</p></article>
    <article><strong>{{ t('home.metricThree') }}</strong><p>{{ t('home.metricThreeText') }}</p></article>
  </section>

  <section class="workflow page-section">
    <span class="eyebrow">{{ t('home.workflow') }}</span>
    <div class="workflow-grid">
      <article><span class="step-number">01</span><h2>{{ t('home.step1') }}</h2><p>{{ t('home.step1Text') }}</p></article>
      <article><span class="step-number">02</span><h2>{{ t('home.step2') }}</h2><p>{{ t('home.step2Text') }}</p></article>
      <article><span class="step-number">03</span><h2>{{ t('home.step3') }}</h2><p>{{ t('home.step3Text') }}</p></article>
    </div>
  </section>

  <section class="support-card page-section">
    <div><span class="eyebrow">{{ t('home.supportEyebrow') }}</span><h2>{{ t('home.supportTitle') }}</h2><p>{{ t('home.supportText') }}</p></div>
    <a class="button" href="https://ko-fi.com/axxon" target="_blank" rel="noopener noreferrer">{{ t('home.supportCta') }}</a>
  </section>
</template>
