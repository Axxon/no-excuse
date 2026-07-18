# Contribuer

Merci de contribuer à no-excuse. Le projet est un prototype ouvert : retours d’expérience, audits, documentation, tests, corrections et pull requests sont les bienvenus.

1. Ouvrir une issue décrivant le problème ou l’évolution.
2. Créer une branche courte depuis `main`.
3. Utiliser Docker et les cibles Make ; ne pas dépendre d’un PHP ou Node hôte.
4. Ajouter ou adapter les tests métier.
5. Exécuter `make validate` avant la pull request.

Les décisions automatiques doivent rester explicables, ne jamais utiliser de caractéristique sensible et préserver une validation humaine finale. Toute évolution de l’API d’ingestion doit mettre à jour `docs/integration-api.md`.
