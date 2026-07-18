# AGENTS.md

- Utiliser les cibles `make` et les conteneurs Docker ; ne pas lancer PHP, Composer, Node ou npm sur l’hôte.
- Exécuter les tests avec `make test` et la validation complète avec `make validate`.
- Ne jamais afficher `.env`, une clé fournisseur, une clé d’ingestion ou le contenu d’un CV dans les logs.
- Aucune route publique ne doit exposer une offre ou une candidature.
- Une instance représente une entreprise ; les annonces appartiennent à l’organisation et sont partagées par son équipe RH.
- Les réglages de prompts et de concurrence des queues sont administrés au niveau de l’organisation.
- Ne jamais exposer les identifiants SQL ; utiliser les UUID publics.
- Toute évolution de l’ingestion met à jour `docs/integration-api.md` et ses tests.
- La sélection finale reste humaine et les critères sensibles ou discriminatoires sont interdits.
- La démo publique n’accepte que les 20 CV fictifs fournis, ne contacte aucune IA payante et ne transmet aucun e-mail réel.
- Une sandbox de démo est isolée par organisation, expire automatiquement et ne doit jamais donner accès au socket Docker.
