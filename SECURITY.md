# Politique de sécurité

Merci de ne pas publier une vulnérabilité dans une issue publique. Utilisez la fonctionnalité **Report a vulnerability** de GitHub sur ce dépôt.

Incluez une description, l’impact, les étapes de reproduction et, si possible, une proposition de correction. Aucune donnée réelle de candidat, clé d’API ou secret d’intégration ne doit apparaître dans le rapport.

Le MVP est destiné à l’évaluation et au développement. Avant une mise en production, configurez HTTPS, un stockage privé chiffré, une messagerie transactionnelle, une politique de rétention/suppression, des sauvegardes, une rotation des secrets, un contrôle d’accès organisationnel et les accords RGPD nécessaires avec chaque fournisseur IA.

## Démo publique

La démo publique ne doit jamais être activée sur une instance contenant de vraies candidatures. Son mode Docker force l’analyse locale, bloque l’ingestion externe et remplace chaque envoi d’e-mail par un événement de prévisualisation. Redis fonctionne sans persistance et ne reçoit que les identifiants des jobs. Les CV fictifs, comptes, jetons et événements sont supprimés avec leur sandbox à expiration.

Le reverse proxy doit appliquer HTTPS, une limitation de débit sur `POST /api/demo/sessions` et ne publier aucun port PostgreSQL, Redis ou API. Ne montez jamais le socket Docker dans l’application.
