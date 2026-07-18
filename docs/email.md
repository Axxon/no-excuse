# Configurer les e-mails

[English](email.en.md) · Français

Par défaut, `MAIL_MAILER=log` n’envoie rien : il est sûr pour le développement et la démo. En production, configurez un SMTP ou un transport API Laravel dans le gestionnaire de secrets de l’infrastructure, jamais dans Git.

Exemple SMTP :

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=change-me
MAIL_PASSWORD=change-me
MAIL_FROM_ADDRESS=recrutement@example.com
MAIL_FROM_NAME="Équipe recrutement"
```

Avec le port `587`, Symfony Mailer négocie automatiquement STARTTLS à partir du schéma `smtp`. Le schéma `smtps` est réservé au TLS implicite, généralement sur le port `465`.

### Démo distante avec Brevo

Le helper `deploy/remote/configure-brevo-secret.sh` demande l’identifiant SMTP, la clé et l’expéditeur sans afficher la clé. Il écrit uniquement dans `~/.config/no-excuse/mailer.env`, avec des permissions `600`. Ce fichier ne doit jamais être ajouté à Git.

Pour appliquer ce secret à une composition distante déjà générée, ajoutez le fichier d’environnement protégé et l’override versionné :

```bash
docker compose \
  --env-file /chemin/vers/.env \
  --env-file ~/.config/no-excuse/mailer.env \
  -f /chemin/vers/docker-compose.yml \
  -f deploy/remote/mailer.override.yml \
  up -d --force-recreate api queue-notifications scheduler
```

L’override ne contient aucun secret. Il active le SMTP uniquement pour l’API, le worker de notifications et le scheduler. Le profil reste indépendant de l’hébergeur.

Rechargez les services (`make restart`), puis vérifiez sans afficher aucun secret :

```bash
make mail-test EMAIL=votre-adresse@example.com
```

Le succès signifie que le transport a accepté le message ; contrôlez aussi sa réception. Configurez SPF, DKIM et DMARC sur le domaine d’envoi. Le worker `notifications` doit rester actif : un échec d’envoi est réessayé et le CV rejeté n’est purgé qu’après un envoi réussi. La démo ne transmet jamais d’e-mail aux faux candidats ; seul l’e-mail opt-in de disponibilité peut partir si un vrai transport est configuré.

Laravel prend en charge SMTP et plusieurs transports API ; sa [documentation Mail officielle](https://laravel.com/docs/13.x/mail) recommande souvent les pilotes API pour leur simplicité et leur rapidité.
