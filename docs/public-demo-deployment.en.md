# Deploy the public demo

English · [Français](public-demo-deployment.md)

This provider-neutral profile is separate from an enterprise installation. It accepts only the repository’s 20 fictional CVs and forces deterministic `NO_EXCUSE_AI_MODE=demo`.

Copy `.env.demo.example` to the untracked `.env.demo`, then set a unique Laravel `APP_KEY`, strong `DB_PASSWORD`, public HTTPS `APP_URL`, and loopback-facing `DEMO_HTTP_PORT`. Do not add paid AI keys.

Run `make demo-prod-deploy`, then point an existing HTTPS reverse proxy to `127.0.0.1:${DEMO_HTTP_PORT}`. Verify `https://demo.example.com/api/demo` and `make demo-prod-ps`.

Sandboxes expire after four hours. `NO_EXCUSE_DEMO_MAX_SESSIONS` defaults to 3. The public status exposes the aggregate `active_sessions` count and `at_capacity`, and the home page displays the current count. Visitors start immediately without providing an email address while capacity remains; only excess visitors may opt into the availability waitlist. `MAIL_MAILER=log` keeps all alerts local. Configure a real transport using the [email guide](email.en.md) to send availability messages. Fictional candidate emails are never sent. The scheduler prunes expired sandboxes every fifteen minutes and notifies waiting visitors when capacity returns.

Recruiters can still select **View candidate email** for a decision-ready fictional application. The authenticated demo-only endpoint renders the actual production Mailable in a sandboxed frame and returns `Cache-Control: no-store`.

The profile runs PostgreSQL, Redis, API, queue workers, scheduler and static frontend without exposing internal service ports. Update by pulling the private source and rerunning the deploy target; do not delete volumes during a routine update.
