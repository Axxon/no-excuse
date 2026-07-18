# no-excuse

```mermaid
sequenceDiagram
    actor C as Candidat
    participant X as LinkedIn / ATS / site carrière
    participant API as API no-excuse
    participant Q1 as Queue de filtrage
    participant IA1 as IA économique
    participant Q2 as Queue de scoring
    participant IA2 as IA fine
    actor RH as Espace RH privé

    C->>X: Dépose sa candidature
    X->>API: POST CV + identité + référence externe
    API-->>X: 202 + référence no-excuse
    API->>Q1: Enregistre la candidature dans le catalogue privé
    Q1->>IA1: Vérifie l'adéquation à l'offre
    alt Hors périmètre
        IA1-->>API: Rejet motivé
        API-->>C: Message défini par le RH
    else Pertinente
        IA1->>Q2: Transmet la candidature qualifiée
        Q2->>IA2: Analyse selon les critères RH
        IA2-->>API: Score, détail et synthèse
        API-->>RH: Met à jour le catalogue privé
    end
    API-->>RH: À la clôture, présente le top 10
    RH->>API: Lit, annote, réordonne et sélectionne
    API-->>C: Décision, score et retour RH optionnel
```

SaaS open source de traitement responsable des candidatures. Les offres et leur catalogue ne sont jamais publics : un recruteur crée une campagne privée, puis LinkedIn, un ATS ou un site carrière transmet chaque CV à une API d’ingestion dédiée.

## Ce que fait le MVP

- catalogue privé de candidatures par offre et accès RH par jeton Sanctum ;
- clé Bearer distincte et révocable pour chaque offre, affichée une seule fois ;
- ingestion multipart compatible avec tout connecteur HTTP et déduplication par source/référence externe ;
- première file avec un modèle économique pour écarter le hors-périmètre ;
- seconde file avec un modèle plus fin pour produire score, critères et synthèse ;
- traitement jusqu’à une date de clôture, puis top 10 réordonnable ;
- lecture des CV historisée, annotations internes et retour candidat optionnel ;
- sélection humaine finale et notification de tous les autres candidats ;
- choix indépendant du fournisseur et du modèle pour les deux étapes IA.

## Stack actuelle

- PHP 8.5.8 et Laravel 13.20 ;
- Vue 3.5, TypeScript 6, Vite 8, Pinia, Vue Router et vue-i18n ;
- PostgreSQL 18.4 et Redis 8.2 ;
- Laravel AI SDK, PDF Parser et Laravel Sanctum ;
- Docker Compose, Make et GitHub Actions.

## Démarrage

Prérequis : Docker, Docker Compose et Make. Aucun runtime PHP ou Node n’est nécessaire sur l’hôte.

```bash
make setup
```

Puis ouvrir :

- interface : http://localhost:5173 ;
- API : http://localhost:18080/api ;
- compte de démonstration : `demo@no-excuse.test` / `demo-password-2026`.

La campagne de démonstration doit recevoir une nouvelle clé depuis son écran d’intégration avant le premier envoi. La clé précédente est révoquée à chaque rotation.

## Brancher LinkedIn, un ATS ou un site carrière

Chaque offre affiche une URL de la forme :

```text
POST /api/v1/intake/{offer_uuid}/applications
Authorization: Bearer {one_time_ingestion_key}
```

Le service source envoie un formulaire multipart avec `source`, `external_reference`, `candidate_name`, `candidate_email`, `cv` et éventuellement `cover_letter`. Consultez le [guide d’intégration](docs/integration-api.md) et le [contrat OpenAPI](docs/openapi.yaml).

> LinkedIn ne propose pas un webhook universel ouvert pour toutes les candidatures. La compatibilité repose sur ce contrat générique : un connecteur LinkedIn autorisé, un ATS partenaire ou une automatisation côté site carrière traduit l’événement vers l’API no-excuse.

## Fournisseurs IA

Le mode `demo` est actif par défaut : il est local, gratuit, déterministe et ne transmet aucun CV. Le mode `live` s’active avec `NO_EXCUSE_AI_MODE=live` et la clé du fournisseur concerné.

Fournisseurs sélectionnables : OpenAI / ChatGPT, Anthropic / Claude, Google Gemini, Mistral, Groq, DeepSeek, OpenRouter, Ollama et toute API compatible OpenAI. Le modèle reste librement éditable par le RH afin d’éviter d’enfermer le produit dans un catalogue vite obsolète.

## Validation

```bash
make validate
```

Les tests backend s’exécutent dans un projet Docker isolé avec SQLite en mémoire. Le lint vérifie Laravel Pint puis le typage et le build de l’interface.

## Principes de sécurité et d’équité

- aucune route publique ne liste ou ne révèle une offre ou une candidature ;
- les clés d’ingestion et de connexion sont stockées sous forme de hash ;
- les CV ne sont servis qu’au recruteur propriétaire ;
- les consignes IA excluent les informations sensibles et critères discriminatoires ;
- le score assiste la décision, mais la sélection finale reste humaine ;
- en mode `live`, le texte du CV quitte l’infrastructure vers les fournisseurs choisis : un accord de traitement des données et une politique de rétention restent indispensables avant production.

Voir aussi [SECURITY.md](SECURITY.md) et [CONTRIBUTING.md](CONTRIBUTING.md).

## Licence

MIT
