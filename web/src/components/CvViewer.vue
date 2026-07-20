<script setup lang="ts">
import { computed, defineAsyncComponent, onUnmounted, ref } from 'vue'
import 'vue-pdf-embed/dist/styles/textLayer.css'
import 'vue-pdf-embed/dist/styles/annotationLayer.css'

const props = defineProps<{ blob: Blob; name: string; text: string | null }>()
const emit = defineEmits<{ close: [] }>()
const VuePdfEmbed = defineAsyncComponent(() => import('vue-pdf-embed'))
const source = URL.createObjectURL(props.blob)
const isPdf = computed(() => props.blob.type === 'application/pdf' || props.name.toLowerCase().endsWith('.pdf'))
const loading = ref(isPdf.value)
const renderingError = ref(false)
onUnmounted(() => URL.revokeObjectURL(source))
function onRendered(): void { loading.value = false }
function onRenderingFailed(): void { loading.value = false; renderingError.value = true }
function download(): void {
  const anchor = document.createElement('a'); anchor.href = source; anchor.download = props.name; anchor.click()
}
</script>

<template>
  <div class="cv-modal" role="dialog" aria-modal="true" :aria-label="name" @click.self="emit('close')">
    <section class="cv-reader">
      <header><strong>{{ name }}</strong><div class="actions"><button class="button button-small button-ghost" @click="download">Télécharger</button><button class="icon-button" aria-label="Fermer" @click="emit('close')">×</button></div></header>
      <div class="cv-reader-body">
        <p v-if="loading" class="cv-reader-message">{{ $t('campaign.cvLoading') }}</p>
        <p v-if="renderingError" class="alert cv-reader-message">{{ $t('campaign.cvRenderError') }}</p>
        <VuePdfEmbed v-if="isPdf && !renderingError" :source="source" annotation-layer text-layer @rendered="onRendered" @loading-failed="onRenderingFailed" @rendering-failed="onRenderingFailed" />
        <pre v-else>{{ text }}</pre>
      </div>
    </section>
  </div>
</template>
