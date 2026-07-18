# Operations, health and backups

English · [Français](operations.md)

The production image serves Laravel with FrankenPHP on internal port `8000`; only the public reverse proxy is exposed. `GET /up` is the application health probe. Docker waits for it before exposing the frontend. **Settings → Operational status**, restricted to owners and administrators, reports all three queue depths, processing and notification failures, closing campaigns and failed Laravel jobs.

Keep screening, scoring, notifications and scheduler as separate workers. `REDIS_QUEUE_RETRY_AFTER` must remain at least `240`, above the maximum analysis timeout, to prevent duplicate execution. The scheduler reconciles stuck mail sends, resumes campaign closure, applies retention and prunes expired tokens. Add external alerts for `/up`, container restarts, disk capacity and non-zero operational failure counters.

## Backups

A complete backup includes PostgreSQL and the `storage` volume that contains CV files. From a standard installation root:

```bash
deploy/backup.sh backups/$(date +%F-%H%M)
```

For another Compose profile, append its options after the destination, for example `-p company -f compose.production.yml --env-file /protected/env`. The script creates `database.dump`, `storage.tar.gz` and SHA-256 checksums under a restrictive `umask 077`. Copy the directory to encrypted off-server storage, use a short retention period and test restores regularly.

Restore only onto fresh volumes with the application stopped: load `database.dump` with `pg_restore`, extract `storage.tar.gz` into the API storage volume, run migrations and check `/up`. Restore is destructive and deliberately remains infrastructure-specific. Keep `APP_KEY` and provider secrets separately; without the original `APP_KEY`, encrypted waitlist addresses cannot be recovered.

Before an update, take a backup, build images, let the `migrate` service complete, then verify `/up` and operational status. A notification marked **needs attention** is not automatically resent because SMTP may have accepted it just before a crash. Check the provider and use **Retry email** explicitly to prevent silent duplicates.
