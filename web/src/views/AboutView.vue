<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiRequest } from '../api'

interface About { author_name: string; author_linkedin_url: string | null; license: string }
const about = ref<About | null>(null)
onMounted(async () => { about.value = await apiRequest<About>('/about') })
</script>

<template>
  <section class="page-section page-heading legal-page">
    <span class="eyebrow">Open source</span>
    <h1>À propos & mentions</h1>
    <p>no-excuse aide les équipes RH à traiter chaque candidature et à répondre, tout en laissant la décision finale à un humain.</p>
    <article class="form-card">
      <h2>Un prototype ouvert</h2>
      <p>no-excuse est un prototype open source construit en une après-midi, en grande partie avec l’aide de l’IA, à partir d’un problème qui concerne directement son auteur : la recherche d’un nouveau poste et le silence qui suit trop souvent les candidatures.</p>
      <p>Le projet ne prétend pas être une solution parfaite ni prête à être exploitée sans audit. Le code est publié pour permettre à chacun de l’examiner, de l’améliorer ou de construire dessus. Les retours, audits de sécurité, discussions et pull requests sont les bienvenus.</p>
      <h2>Auteur</h2>
      <p v-if="about">Créé par <strong>{{ about.author_name }}</strong><template v-if="about.author_linkedin_url"> · <a :href="about.author_linkedin_url" target="_blank" rel="noreferrer">Profil LinkedIn</a></template>.</p>
      <h2>Licence</h2>
      <p>Le code est actuellement distribué sous licence {{ about?.license ?? 'MIT' }}. Les conditions détaillées figurent dans le dépôt.</p>
      <h2>Responsabilité</h2>
      <p>Ce logiciel est fourni sans garantie. Il assiste la priorisation ; l’employeur reste responsable du traitement, de la conformité et de toute décision de recrutement. Ce texte est un modèle informatif à faire valider juridiquement avant une exploitation commerciale.</p>
      <h2>Soutenir le travail</h2>
      <p>no-excuse et Sonomundi sont des projets indépendants développés ouvertement. Vous pouvez contribuer à leur temps de développement et à leur infrastructure.</p>
      <a class="button" href="https://ko-fi.com/axxon" target="_blank" rel="noopener noreferrer">Soutenir sur Ko-fi</a>
    </article>
  </section>
</template>
