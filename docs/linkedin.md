# Brancher LinkedIn

[English](linkedin.en.md) · Français

no-excuse est le récepteur privé en aval : il ne scrape pas LinkedIn et n’expose ni annonce ni suivi de candidature.

## Chemin recommandé

```text
Candidat → LinkedIn Apply → ATS/connecteur certifié → API no-excuse
```

1. Le RH crée l’annonce dans no-excuse et copie l’URL d’ingestion ainsi que la clé Bearer.
2. L’ATS ou middleware reçoit la candidature LinkedIn.
3. Il télécharge le CV avec les droits accordés, puis envoie le formulaire multipart décrit dans [l’API d’ingestion](integration-api.md), avec `source=linkedin` et l’identifiant LinkedIn comme `external_reference`.
4. Il conserve `application_reference` pour l’audit et applique des retries exponentiels sur les seules erreurs réseau/5xx.

L’intégration officielle **Apply Connect** de LinkedIn relie LinkedIn à un ATS partenaire. Elle permet de fournir un `jobApplicationWebhookUrl`, mais l’accès production exige les permissions et la certification partenaire LinkedIn : ce n’est pas un webhook universel disponible à toute installation. Références officielles : [vue d’ensemble Apply Connect](https://learn.microsoft.com/en-us/linkedin/talent/apply-connect/apply-connect-overview), [création des offres et webhook](https://learn.microsoft.com/en-us/linkedin/talent/apply-connect/create-apply-connect-jobs), [certification](https://learn.microsoft.com/en-us/linkedin/talent/apply-connect/apply-connect-certification).

Sans accès partenaire, utilisez l’ATS déjà connecté à LinkedIn ou redirigez vers le formulaire carrière de l’entreprise, qui relaie ensuite le CV à no-excuse. N’utilisez pas de scraping ni de cookie de session LinkedIn.
