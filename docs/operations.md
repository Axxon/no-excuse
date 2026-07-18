# Exploitation, santé et sauvegardes

[English](operations.en.md) · Français

L’image de production sert Laravel avec FrankenPHP sur le port interne `8000`. Le reverse proxy public reste le seul composant exposé. `GET /up` vérifie que Laravel démarre ; Docker l’utilise pour ne rendre le frontend disponible qu’après l’API. L’écran **Configuration → État opérationnel**, réservé aux responsables et administrateurs, affiche la profondeur des trois files, les analyses et e-mails en erreur, les campagnes en clôture et les jobs Laravel échoués.

Les workers doivent rester séparés : filtrage, scoring, notifications et scheduler. `REDIS_QUEUE_RETRY_AFTER` vaut au moins `240`, au-dessus du timeout maximal d’analyse, afin qu’un job lent ne soit pas exécuté deux fois. Le scheduler réconcilie les e-mails restés en cours, poursuit les clôtures, applique la rétention et purge les jetons expirés. Configurez une alerte externe sur `/up`, les redémarrages Docker, l’espace disque et les compteurs non nuls de l’état opérationnel.

## Sauvegarder

Une sauvegarde cohérente comprend PostgreSQL **et** le volume `storage` contenant les CV. Depuis la racine d’une installation standard :

```bash
deploy/backup.sh backups/$(date +%F-%H%M)
```

Pour un autre profil Compose, passez ses options après la destination, par exemple `-p entreprise -f compose.production.yml --env-file /chemin/protege/env`. Le script produit `database.dump`, `storage.tar.gz` et leurs sommes SHA-256 avec un `umask 077`. Copiez ensuite ce dossier vers un stockage chiffré hors du serveur, appliquez une durée de conservation courte et testez régulièrement la restauration.

La restauration doit se faire sur des volumes neufs, avec l’application arrêtée : restaurer d’abord `database.dump` avec `pg_restore`, puis extraire `storage.tar.gz` dans le volume de l’API, lancer les migrations et vérifier `/up`. Une restauration écrase des données ; elle n’est volontairement pas automatisée sans procédure propre à l’infrastructure. Conservez aussi séparément `APP_KEY` et les secrets : sans la même `APP_KEY`, les adresses chiffrées de la liste d’attente ne sont pas récupérables.

## Mise à jour et incident

Avant une mise à jour : sauvegarde, construction des images, migration par le service `migrate`, puis contrôle de `/up` et de l’état opérationnel. Un e-mail marqué **à vérifier** n’est pas renvoyé automatiquement : un SMTP peut avoir accepté le message juste avant une coupure. Le RH vérifie le fournisseur puis choisit explicitement **Relancer l’e-mail**, ce qui évite les doublons silencieux.
