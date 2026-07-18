# Deploy the public demo

English · [Français](public-demo-deployment.md)

This provider-neutral profile is separate from an enterprise installation. It accepts only the repository’s 20 fictional CVs and forces `NO_EXCUSE_AI_MODE=demo`. The 20 PDF files are materialized once in shared storage. Each sandbox keeps isolated applications and events, while workers replay precomputed results without extracting or analysing those PDFs again.

Copy `.env.demo.example` to the untracked `.env.demo`, then set a unique Laravel `APP_KEY`, strong `DB_PASSWORD`, public HTTPS `APP_URL`, and loopback-facing `DEMO_HTTP_PORT`. Do not add paid AI keys.

Run `make demo-prod-deploy`, then point an existing HTTPS reverse proxy to `127.0.0.1:${DEMO_HTTP_PORT}`. Verify `https://demo.example.com/api/demo` and `make demo-prod-ps`.

Sandboxes expire after four hours. `NO_EXCUSE_DEMO_MAX_SESSIONS` defaults to 20 and is hard-capped at 20. The public status exposes `active_sessions`, `max_sessions`, `at_capacity`, `waitlist_count`, and `waitlist`, and the home page displays the current count alongside the configured maximum without showing any company-instance login action. Visitors start immediately without providing an email address while capacity remains, but the same visitor cannot create or reset a second sandbox during its lifetime. The **Release sandbox** CTA immediately destroys the temporary organization, files and tokens before signing the visitor out; `/login` offers the same direct launch instead of the company login form. Once inside, visitors can inspect the detailed backoffice configuration in read-only mode while backend mutations remain forbidden. Only excess visitors may opt into the availability waitlist. Public entries contain only a position and a random reference—never even a masked address. A browser recognizes its own reference as **You**. The full address is encrypted at rest and deduplicated with a keyed, non-reversible HMAC.

When capacity returns, the first visitor receives a personal URL that reserves one slot for 30 minutes. Reservation is claimed atomically before mail is sent, preventing two visitors from consuming the same slot; an SMTP failure returns it to the queue. `MAIL_MAILER=log` keeps all alerts local. Configure a real transport using the [email guide](email.en.md) to send availability messages. Fictional candidate emails are never sent. The scheduler prunes expired sandboxes every fifteen minutes and notifies waiting visitors when capacity returns.

Recruiters can still select **View candidate email** for a decision-ready fictional application. The authenticated demo-only endpoint renders the actual production Mailable in a sandboxed frame and returns `Cache-Control: no-store`.

The profile runs PostgreSQL, Redis, API, queue workers, scheduler and static frontend without exposing internal service ports. Update by pulling the private source and rerunning the deploy target; do not delete volumes during a routine update.
