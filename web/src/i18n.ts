import { createI18n } from 'vue-i18n'

export const messages = {
  fr: {
    nav: { main: 'Navigation principale', dashboard: 'Suivi', settings: 'Configuration', recruiter: 'Espace RH', logout: 'Se déconnecter' },
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
    auth: { title: 'Espace RH privé', lead: 'Retrouvez les annonces et candidatures de votre entreprise.', login: 'Se connecter', name: 'Nom', email: 'E-mail', password: 'Mot de passe', confirmation: 'Confirmation', promise: 'Une décision assistée, jamais automatisée.', teamOnly: 'Accès réservé à votre équipe' },
    setup: { eyebrow: 'Première installation', title: 'Prêt en trois étapes.', lead: 'Créez l’entreprise et le premier compte responsable. Aucun réglage technique n’est nécessaire ici.', step1: 'Entreprise et responsable', step2: 'IA, e-mails et équipe', step3: 'Première annonce', companyAndOwner: 'Créer votre espace', company: 'Nom de l’entreprise', ownerName: 'Votre nom', continue: 'Créer et continuer', passwordMismatch: 'Les deux mots de passe ne correspondent pas.' },
    settings: { eyebrow: 'Étape 2 · Configuration', title: 'Une configuration, toute l’équipe.', lead: 'Ces réglages s’appliquent aux annonces de l’entreprise. Les clés secrètes des fournisseurs restent dans la configuration serveur.', company: 'Entreprise et automatisation', sender: 'Nom affiché dans les e-mails', replyTo: 'Adresse de réponse', filterProvider: 'IA de filtrage par défaut', scoreProvider: 'IA d’analyse par défaut', demoMode: 'Mode démo — aucune clé requise', liveMode: 'Mode IA connecté', secretHelp: 'Les clés sont injectées par le développeur dans le serveur. Cette page vérifie leur présence, mais ne reçoit et n’affiche jamais leur valeur.', configured: 'configuré', missing: 'clé manquante', models: 'Choisir précisément les modèles (optionnel)', filterModel: 'Modèle de filtrage', scoreModel: 'Modèle d’analyse', velocity: 'Vélocité de traitement', velocityLead: 'Les workers s’ajustent automatiquement quelques secondes après l’enregistrement.', filterWorkers: 'Filtrages simultanés', scoreWorkers: 'Analyses simultanées', prompts: 'Instructions données aux IA', filterPrompt: 'Prompt de filtrage initial', scorePrompt: 'Prompt de matching et scoring', filterPromptHelp: 'Définit ce qui mérite une analyse approfondie et ce qui est réellement hors périmètre.', scorePromptHelp: 'Définit la méthode de comparaison, de justification et de calcul du score.', saved: 'Configuration enregistrée.', team: 'Équipe RH', teamLead: 'Tous les membres voient les mêmes annonces et le même suivi.', add: 'Ajouter', role: 'Rôle', owner: 'Responsable', admin: 'Administrateur', recruiter: 'Recruteur', viewer: 'Lecture seule', temporaryPassword: 'Mot de passe temporaire', createAccess: 'Créer l’accès' },
    dashboard: { title: 'Annonces et suivi', lead: 'Créez une annonce, connectez sa source, puis suivez uniquement ce qui demande votre attention.', welcome: 'Bonjour, {name}', newOffer: 'Créer une annonce', titleField: 'Intitulé du poste', company: 'Entreprise', location: 'Localisation', description: 'Description du poste', criteria: 'Compétences recherchées, séparées par des virgules', criteriaExample: 'Laravel, relation client, anglais…', advanced: 'Messages et IA — réglages avancés', companyDefault: 'Réglage de l’entreprise', rejection: 'Message de rejet hors périmètre', finalRejection: 'Message final aux candidats non retenus', screeningProvider: 'IA de filtrage', scoringProvider: 'IA d’analyse', create: 'Créer l’annonce', applications: '{count} candidatures', empty: 'Aucune annonce pour le moment.', firstOffer: 'Créer la première annonce', integrationCreated: 'Annonce créée · connexion suivante', integrationWarning: 'Copiez cette clé maintenant : elle ne sera plus affichée.', endpoint: 'Point d’entrée API', secret: 'Clé Bearer' },
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
