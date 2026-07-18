<script setup lang="ts">
defineProps<{ html: string; candidate: string }>()
const emit = defineEmits<{ close: [] }>()
</script>

<template>
  <div class="mail-modal" role="dialog" aria-modal="true" :aria-label="candidate" @click.self="emit('close')">
    <section class="mail-reader">
      <header><div><span class="eyebrow">{{ $t('demo.mailPreviewLabel') }}</span><strong>{{ candidate }}</strong></div><button class="icon-button" :aria-label="$t('common.close')" @click="emit('close')">×</button></header>
      <iframe sandbox="" :srcdoc="html" :title="$t('demo.mailPreviewTitle', { candidate })" />
    </section>
  </div>
</template>
