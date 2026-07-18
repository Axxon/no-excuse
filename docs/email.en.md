# Configure email

English · [Français](email.md)

`MAIL_MAILER=log` sends nothing and is safe for development/demo. In production, configure SMTP or a Laravel API transport through infrastructure secrets, never Git.

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=change-me
MAIL_PASSWORD=change-me
MAIL_FROM_ADDRESS=recruitment@example.com
MAIL_FROM_NAME="Recruitment team"
```

Reload services with `make restart`, then run `make mail-test EMAIL=your-address@example.com`. A successful command means the transport accepted the message; also verify delivery. Configure SPF, DKIM and DMARC. Keep the `notifications` worker active: failed deliveries are retried and a rejected CV is purged only after successful delivery. The demo never emails fictional candidates; only the opt-in availability notification can be sent when a real transport is configured.

See the official [Laravel 13 Mail documentation](https://laravel.com/docs/13.x/mail).
