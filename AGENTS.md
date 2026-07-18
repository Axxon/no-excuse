# AGENTS.md

- Utiliser les cibles `make` et les conteneurs Docker ; ne pas lancer PHP, Composer, Node ou npm sur l’hôte.
- Exécuter les tests avec `make test` et la validation complète avec `make validate`.
- Ne jamais afficher `.env`, une clé fournisseur, une clé d’ingestion ou le contenu d’un CV dans les logs.
- Aucune route publique ne doit exposer une offre ou une candidature.
- Ne jamais exposer les identifiants SQL ; utiliser les UUID publics.
- Toute évolution de l’ingestion met à jour `docs/integration-api.md` et ses tests.
- La sélection finale reste humaine et les critères sensibles ou discriminatoires sont interdits.
