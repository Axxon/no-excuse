# Configure email

English · [Français](email.md)

`MAIL_MAILER=log` sends nothing and is safe for development/demo. In production, configure SMTP or a Laravel API transport through infrastructure secrets, never Git.

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=change-me
MAIL_PASSWORD=change-me
MAIL_FROM_ADDRESS=recruitment@example.com
MAIL_FROM_NAME="Recruitment team"
```

On port `587`, Symfony Mailer automatically negotiates STARTTLS from the `smtp` scheme. Use `smtps` only for implicit TLS, typically on port `465`.

### Remote demo with Brevo

The `deploy/remote/configure-brevo-secret.sh` helper prompts for the SMTP login, key, and authenticated sender without echoing the key. It writes only to `~/.config/no-excuse/mailer.env` with mode `600`. Never add that file to Git.

To apply the protected secret to an already generated remote Compose stack, add the secret environment file and the tracked override:

```bash
docker compose \
  --env-file /path/to/.env \
  --env-file ~/.config/no-excuse/mailer.env \
  -f /path/to/docker-compose.yml \
  -f deploy/remote/mailer.override.yml \
  up -d --force-recreate api queue-notifications scheduler
```

The override contains no secret. It enables SMTP only for the API, notification worker, and scheduler, and remains hosting-provider neutral.

Reload services with `make restart`, then run `make mail-test EMAIL=your-address@example.com`. A successful command means the transport accepted the message; also verify delivery. Configure SPF, DKIM and DMARC. Keep the `notifications` worker active: failed deliveries are retried and a rejected CV is purged only after successful delivery. The demo never emails fictional candidates; only the opt-in availability notification can be sent when a real transport is configured.

See the official [Laravel 13 Mail documentation](https://laravel.com/docs/13.x/mail).
