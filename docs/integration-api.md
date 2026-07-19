# API d’ingestion

[English](integration-api.en.md) · Français

L’API d’ingestion relie une source externe à un catalogue privé no-excuse. Elle ne permet ni de consulter une offre, ni de lire une candidature.

## 1. Obtenir les identifiants

Le recruteur crée une offre depuis son tableau de bord. no-excuse affiche alors une URL d’ingestion et une clé secrète une seule fois. Il peut régénérer cette clé à tout moment ; la précédente devient immédiatement invalide.

## 2. Envoyer une candidature

```bash
curl --request POST 'https://no-excuse.example/api/v1/intake/OFFER_UUID/applications' \
  --header 'Authorization: Bearer INGESTION_KEY' \
  --header 'Accept: application/json' \
  --form 'source=linkedin' \
  --form 'external_reference=linkedin-application-1234' \
  --form 'candidate_name=Ada Martin' \
  --form 'candidate_email=ada@example.test' \
  --form 'cover_letter=Message facultatif' \
  --form 'cv=@/path/to/cv.pdf;type=application/pdf'
```

Champs :

| Champ | Obligatoire | Contrainte |
| --- | --- | --- |
| `source` | oui | nom stable du connecteur, 80 caractères max. |
| `external_reference` | oui | identifiant stable de la candidature côté source, 190 caractères max. |
| `candidate_name` | oui | 160 caractères max. |
| `candidate_email` | oui | adresse e-mail valide. |
| `cv` | oui | PDF non chiffré uniquement (`application/pdf`), 10 Mio et 100 pages max. |
| `cover_letter` | non | texte, 10 000 caractères max. |

Le nom et l'adresse e-mail transmis séparément servent aussi à pseudonymiser le texte extrait du CV avant toute analyse IA en mode `live`. Le service local masque leurs variantes ainsi que d'autres coordonnées et entités personnelles. Si cette étape échoue, la candidature passe en échec de traitement et aucun appel au fournisseur IA n'est effectué ; elle peut ensuite être relancée par un RH après correction. Le PDF original reste privé et n'est jamais transmis au fournisseur par ce pipeline.

Réponse initiale, HTTP 202 :

```json
{
  "application_reference": "019b...",
  "status": "accepted",
  "duplicate": false
}
```

Le couple `source` + `external_reference` est idempotent dans une offre. Un nouvel envoi du même couple retourne HTTP 200, la référence initiale et `duplicate: true`, sans créer une seconde candidature. Le filtre initial ne décide pas seul : il place un cas apparemment hors périmètre dans **Rejet à confirmer** avec une explication factuelle. Le RH confirme le rejet ou poursuit l’analyse. Après confirmation, la réponse est immédiatement placée dans la file d’e-mail ; la fiche indique `E-mail en attente`, `Envoi en cours`, puis `E-mail envoyé` ou `E-mail à vérifier`.

La campagne passe en **Clôture en cours** si des analyses, confirmations ou reprises restent attendues. Le top 10 n’est construit qu’une fois toutes les candidatures stabilisées. Le scoring compare uniquement les preuves professionnelles aux critères annoncés ; il n’effectue jamais la sélection finale.

## 3. Adapter une source

Le connecteur doit :

1. écouter l’événement de candidature fourni par la source ;
2. télécharger le CV selon les droits accordés par cette source ;
3. convertir les champs vers le formulaire ci-dessus ;
4. conserver `application_reference` dans sa propre journalisation ;
5. réessayer uniquement les erreurs réseau et réponses 5xx avec temporisation exponentielle.

Réponses utiles : `404` pour une URL ou une clé non reconnue, `409` si la campagne est fermée, `422` si le formulaire est invalide et `429` si la limite de débit est dépassée.

## Sécurité opérationnelle

- transmettre la clé uniquement via HTTPS et le header `Authorization` ;
- stocker la clé dans le coffre à secrets du connecteur ;
- ne jamais écrire la clé, le CV ou le contenu de la lettre dans les logs ;
- faire tourner immédiatement la clé après une exposition suspectée ;
- utiliser une offre distincte et donc une clé distincte par catalogue.

Pour LinkedIn, ne collectez pas les pages par scraping : suivez le [guide LinkedIn](linkedin.md).
