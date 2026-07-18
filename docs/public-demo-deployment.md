# Déployer la démo publique

Cette composition est distincte d’une installation d’entreprise. Elle ne traite que les CV fictifs inclus dans le dépôt et force `NO_EXCUSE_AI_MODE=demo`.

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

La réponse publique doit indiquer `enabled: true`. Depuis la page principale, **Lancer la démo** doit créer une sandbox, afficher 20 candidatures et faire évoluer leurs statuts pendant environ quarante secondes.

## Exploitation

- `make demo-prod-logs` suit les composants utiles sans afficher les CV ;
- le scheduler exécute `demo:prune` toutes les quinze minutes ;
- les sandboxes expirent après quatre heures par défaut ;
- `NO_EXCUSE_DEMO_MAX_SESSIONS` borne les espaces actifs ;
- les volumes PostgreSQL et CV ne sont jamais partagés avec une instance d’entreprise.

Pour une mise à jour, récupérez le nouveau commit privé puis relancez `make demo-prod-deploy`. Ne supprimez pas les volumes pendant une mise à jour ordinaire.

## Déploiement distant depuis une archive privée

`compose.remote.yml` est indépendant du fournisseur d'hébergement. Il fonctionne
avec tout moteur Docker Compose capable de construire depuis une archive HTTP.
Le contexte `SOURCE_ARCHIVE_URL` peut être une URL GitHub signée et éphémère :
le dépôt reste privé et aucun jeton GitHub n'est enregistré sur le serveur. Les
Dockerfiles sous `deploy/remote/` construisent l'API et le front depuis la racine
de cette archive.

Le service `migrate` termine les migrations avant le démarrage de l'API et des
workers. Le front reste publié uniquement sur `127.0.0.1:8088`; le reverse proxy
de l'hôte termine TLS pour `no-excuse.pro`.

Sur Hostinger, ce même fichier peut être fourni au Docker Manager. Sur un autre
VPS, il peut être utilisé directement avec Docker Compose, sans adaptation du
code applicatif.
