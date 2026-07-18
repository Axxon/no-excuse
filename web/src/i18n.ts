import { createI18n } from 'vue-i18n'

export const messages = {
  fr: {
    nav: { main: 'Navigation principale', dashboard: 'Tableau de bord', recruiter: 'Espace recruteur', logout: 'Se déconnecter' },
    footer: { promise: 'Chaque candidature mérite une réponse.' },
    home: {
      eyebrow: 'Recrutement responsable, assisté par IA',
      title: 'Moins de bruit. Plus de réponses.',
      lead: 'no-excuse reçoit les candidatures de vos canaux existants, filtre les profils hors périmètre et prépare une décision humaine explicite.',
      recruiterCta: 'Accéder à l’espace RH', integrationCta: 'Documentation API',
      metricOne: '2 files IA', metricOneText: 'Un filtre économique, puis une analyse fine.',
      metricTwo: 'Top 10', metricTwoText: 'Une sélection lisible pour la décision humaine.',
      metricThree: '100 %', metricThreeText: 'Des candidats informés à la fin du processus.',
      workflow: 'Un processus privé et traçable', step1: 'Connecter', step1Text: 'LinkedIn, un ATS ou votre site transmet le CV à l’API sécurisée.',
      step2: 'Qualifier', step2Text: 'Deux modèles configurables filtrent puis scorent.',
      step3: 'Décider', step3Text: 'Le RH annote, réordonne et choisit humainement.',
    },
    offers: { title: 'Offres ouvertes', lead: 'Des campagnes avec une date de clôture et un engagement de réponse.', empty: 'Aucune offre ouverte pour le moment.', apply: 'Voir et candidater', closes: 'Clôture le' },
    apply: { title: 'Candidater', criteria: 'Critères annoncés', name: 'Nom complet', email: 'Adresse e-mail', letter: 'Message de motivation', cv: 'CV au format PDF ou texte', submit: 'Envoyer ma candidature', success: 'Candidature enregistrée', tokenWarning: 'Copiez ce jeton maintenant : il ne sera plus affiché.', track: 'Suivre cette candidature' },
    auth: { title: 'Espace recruteur', lead: 'Pilotez vos campagnes et gardez la décision humaine.', login: 'Connexion', register: 'Créer un compte', name: 'Nom', email: 'E-mail', password: 'Mot de passe', confirmation: 'Confirmation', switchLogin: 'J’ai déjà un compte', switchRegister: 'Créer mon compte' },
    dashboard: { title: 'Campagnes privées', welcome: 'Bonjour, {name}', newOffer: 'Nouvelle offre', titleField: 'Intitulé', company: 'Entreprise', location: 'Localisation', description: 'Description', criteria: 'Critères séparés par des virgules', rejection: 'Message de rejet hors périmètre', finalRejection: 'Message final aux candidats non retenus', screeningProvider: 'Fournisseur du filtre', screeningModel: 'Modèle économique (optionnel)', scoringProvider: 'Fournisseur du scoring', scoringModel: 'Modèle fin (optionnel)', create: 'Créer le catalogue', applications: '{count} candidatures', empty: 'Créez votre premier catalogue de candidatures.', integrationCreated: 'Accès d’intégration créé', integrationWarning: 'Copiez cette clé maintenant : elle ne sera plus affichée.', endpoint: 'Point d’entrée API', secret: 'Clé Bearer' },
    campaign: { open: 'Ouvrir les réceptions', close: 'Clôturer et produire le top 10', closingDate: 'Date de clôture', applications: 'Candidatures', empty: 'Aucune candidature reçue.', viewCv: 'Lire le CV', addNote: 'Ajouter une note interne', feedback: 'Retour partageable au candidat', saveFeedback: 'Enregistrer le retour', select: 'Sélectionner ce candidat', moveUp: 'Monter', moveDown: 'Descendre', confirmSelect: 'Confirmer la sélection finale ? Les autres candidats recevront leur réponse.', score: 'Score', unread: 'Non lu', processing: 'Traitement en cours', integration: 'Connexion ATS / job board', integrationLead: 'Ce point d’entrée privé reçoit les CV depuis LinkedIn, votre ATS ou votre site.', rotateKey: 'Générer une nouvelle clé', rotateWarning: 'La clé précédente sera immédiatement révoquée. Continuer ?', source: 'Source' },
    tracking: { title: 'Suivre une candidature', lead: 'Utilisez l’identifiant et le jeton privés reçus lors du dépôt.', uuid: 'Identifiant de candidature', token: 'Jeton privé', submit: 'Consulter le statut', score: 'Score final', feedback: 'Retour du recruteur' },
    status: { draft: 'Brouillon', open: 'Ouverte', closed: 'Top 10 prêt', selection_made: 'Sélection terminée', received: 'Reçue', screening: 'Filtrage', qualified: 'Qualifiée', scoring: 'Analyse fine', scored: 'Scorée', shortlisted: 'Top 10', selected: 'Sélectionnée', rejected_out_of_scope: 'Hors périmètre', rejected_final: 'Non retenue', processing_failed: 'À relancer' },
    common: { loading: 'Chargement…', error: 'Une erreur est survenue.', save: 'Enregistrer', cancel: 'Annuler' },
  },
}

export const i18n = createI18n({
  legacy: false,
  locale: 'fr',
  fallbackLocale: 'fr',
  messages,
  datetimeFormats: { fr: { short: { year: 'numeric', month: 'short', day: 'numeric' } } },
})
