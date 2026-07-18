<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { apiRequest, type DemoStatus } from '../api'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n()
const router = useRouter(); const auth = useAuthStore(); const demo = ref<DemoStatus | null>(null); const starting = ref(false); const error = ref('')
onMounted(async () => { try { demo.value = await apiRequest<DemoStatus>('/demo') } catch { demo.value = null } })
async function startDemo(): Promise<void> {
  starting.value = true; error.value = ''
  try { const offerUuid = await auth.startDemo(); await router.push(`/dashboard/offers/${offerUuid}`) }
  catch (caught) { error.value = caught instanceof Error ? caught.message : t('common.error') }
  finally { starting.value = false }
}
</script>

<template>
  <section class="hero page-section">
    <div class="hero-copy">
      <span class="eyebrow">{{ t('home.eyebrow') }}</span>
      <h1>{{ t('home.title') }}</h1>
      <p class="hero-lead">{{ t('home.lead') }}</p>
      <div class="actions">
        <button v-if="demo?.enabled" class="button" :disabled="starting" @click="startDemo">{{ starting ? t('home.demoStarting') : t('home.demoCta') }}</button>
        <RouterLink class="button button-ghost" to="/login">{{ t('home.recruiterCta') }}</RouterLink>
        <a class="button button-ghost" href="https://github.com/Axxon/no-excuse" target="_blank" rel="noreferrer">{{ t('home.integrationCta') }}</a>
      </div>
      <p v-if="demo?.enabled" class="demo-promise">{{ t('home.demoPromise', { count: demo.candidate_count, hours: demo.lifetime_hours }) }}</p>
      <p v-if="error" class="alert">{{ error }}</p>
    </div>
    <div class="hero-visual" aria-hidden="true">
      <div class="signal-card signal-card-main"><span>94</span><small>match</small></div>
      <div class="signal-card signal-card-left"><span>10</span><small>top</small></div>
      <div class="signal-card signal-card-right"><span>✓</span><small>réponse</small></div>
      <div class="orbit"></div>
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
</template>
