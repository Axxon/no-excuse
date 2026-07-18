<script setup lang="ts">
import { computed, defineAsyncComponent, onUnmounted } from 'vue'
import 'vue-pdf-embed/dist/styles/textLayer.css'
import 'vue-pdf-embed/dist/styles/annotationLayer.css'

const props = defineProps<{ blob: Blob; name: string; text: string | null }>()
const emit = defineEmits<{ close: [] }>()
const VuePdfEmbed = defineAsyncComponent(() => import('vue-pdf-embed'))
const source = URL.createObjectURL(props.blob)
const isPdf = computed(() => props.blob.type === 'application/pdf' || props.name.toLowerCase().endsWith('.pdf'))
onUnmounted(() => URL.revokeObjectURL(source))
function download(): void {
  const anchor = document.createElement('a'); anchor.href = source; anchor.download = props.name; anchor.click()
}
</script>

<template>
  <div class="cv-modal" role="dialog" aria-modal="true" :aria-label="name" @click.self="emit('close')">
    <section class="cv-reader">
      <header><strong>{{ name }}</strong><div class="actions"><button class="button button-small button-ghost" @click="download">Télécharger</button><button class="icon-button" aria-label="Fermer" @click="emit('close')">×</button></div></header>
      <div class="cv-reader-body">
        <VuePdfEmbed v-if="isPdf" :source="source" annotation-layer text-layer />
        <pre v-else>{{ text }}</pre>
      </div>
    </section>
  </div>
</template>
