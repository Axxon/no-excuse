# Déployer la démo publique

[English](public-demo-deployment.en.md) · Français

Cette composition est distincte d’une installation d’entreprise. Elle ne traite que les CV fictifs inclus dans le dépôt et force `NO_EXCUSE_AI_MODE=demo`. Les 20 PDF sont matérialisés une seule fois dans le volume partagé. Chaque sandbox possède ses propres candidatures et événements, mais les workers rejouent des résultats pré-calculés sans extraire ni réanalyser les PDF.

## Préparer le serveur

Le VPS doit disposer de Docker avec le plugin Compose et d’un reverse proxy HTTPS existant. Clonez le dépôt privé avec une deploy key en lecture seule, puis créez le fichier non versionné :

```bash
cp .env.demo.example .env.demo
```

Renseignez dans `.env.demo` :

- `APP_KEY` : une clé Laravel aléatoire et unique ;
- `DB_PASSWORD` : un mot de passe PostgreSQL long et unique ;
- `APP_URL` : l’URL HTTPS publique ;
- `DEMO_HTTP_PORT` : un port lié uniquement à l’interface locale du reverse proxy si possible.

Ne placez aucune clé OpenAI, Anthropic ou autre fournisseur dans cette démo.

## Lancer ou mettre à jour

```bash
make demo-prod-deploy
```

Cette cible construit les images PHP 8.5 et Node 24, démarre PostgreSQL 18, Redis 8 sans persistance, l’API, les trois rails de queue, le scheduler et le frontend statique, puis applique les migrations et optimise Laravel.

Configurez ensuite le reverse proxy HTTPS vers `127.0.0.1:${DEMO_HTTP_PORT}`. Le port ne doit pas être exposé directement à Internet lorsque le VPS possède déjà un proxy frontal.

## Vérifications

```bash
curl -fsS https://demo.example.com/api/demo
make demo-prod-ps
```

La réponse publique doit indiquer `enabled: true`, `active_sessions`, `max_sessions`, `at_capacity`, `waitlist_count` et `waitlist`. La page principale affiche le nombre agrégé de sandboxes actuellement servies avec la capacité maximale configurée et ne présente aucun bouton de connexion à une instance d’entreprise. Tant que `at_capacity` vaut `false`, **Lancer la démo** crée immédiatement une sandbox sans demander d’e-mail, affiche 20 candidatures et fait évoluer leurs statuts pendant environ quarante secondes. Un même visiteur ne peut créer qu’une sandbox pendant sa durée de vie et l’action de réinitialisation n’est pas exposée. Le CTA **Libérer la sandbox** détruit immédiatement l’organisation temporaire, ses fichiers et ses jetons avant de déconnecter le visiteur. Sur `/login`, le lancement direct remplace le formulaire de connexion d’entreprise. La sandbox expose ensuite la configuration détaillée du backoffice en lecture seule ; le backend continue de refuser toute modification. Le formulaire de liste d’attente n’apparaît qu’à saturation. La file publique ne contient que la position et une adresse masquée, par exemple `s*******n.g***s@g***l.com` ; l’adresse complète reste chiffrée en base et n’est jamais renvoyée par l’API publique.

## Exploitation

- `make demo-prod-logs` suit les composants utiles sans afficher les CV ;
- le scheduler exécute `demo:prune` toutes les quinze minutes ;
- les sandboxes expirent après quatre heures par défaut ;
- `NO_EXCUSE_DEMO_MAX_SESSIONS` borne les espaces actifs, avec une limite de sécurité absolue fixée à `20` ;
- la valeur par défaut est `20` ; les visiteurs supplémentaires rejoignent volontairement la liste d’attente ;
- avec `MAIL_MAILER=log`, aucune alerte ne quitte le serveur ; configurez un vrai transport selon le [guide e-mail](email.md) pour activer les alertes de disponibilité ;
- les réponses aux faux candidats restent consultables avec **Voir l’e-mail candidat** : l’API rend le vrai Mailable de production, uniquement pour l’organisation de démo authentifiée et sans mise en cache ;
- les volumes PostgreSQL et CV ne sont jamais partagés avec une instance d’entreprise.

Pour une mise à jour, récupérez le nouveau commit privé puis relancez `make demo-prod-deploy`. Ne supprimez pas les volumes pendant une mise à jour ordinaire.

## Déploiement distant depuis une archive privée

`compose.remote.yml` est indépendant du fournisseur d'hébergement. Il fonctionne
avec tout moteur Docker Compose capable de construire depuis une archive HTTP.
Par défaut, le contexte de construction est le dépôt courant (`.`). La variable
`SOURCE_ARCHIVE_URL` permet aussi d'utiliser une archive distante, par exemple
une URL GitHub signée et éphémère :
le dépôt reste privé et aucun jeton GitHub n'est enregistré sur le serveur. Les
Dockerfiles sous `deploy/remote/` construisent l'API et le front depuis la racine
de cette archive.

Le service `migrate` termine les migrations avant le démarrage de l'API et des
workers. Le front reste publié uniquement sur `127.0.0.1:8088`; le reverse proxy
de l'hôte termine TLS pour `no-excuse.pro`.

Le même fichier peut être fourni à un panneau Docker managé ou utilisé
directement avec Docker Compose sur un VPS, sans adaptation du code
applicatif.
