# no-excuse

English · [Français](README.md)

Recruitment has become a vicious circle: faced with sometimes fictitious job posts and applications that receive no answer, candidates automate their submissions. Recruiters then receive an ever-growing volume of applications that is difficult to process, creating even more silence.

no-excuse breaks that loop: recruiters save time by screening and prioritising applications, while every candidate receives a clear answer whether selected or not. Relevant profiles are scored against each criterion and presented in a top 10 for a decision that remains human.

Open-source, self-hosted recruitment workflow for accountable candidate processing. Existing job boards, ATSs or career sites send applications to a private ingestion API. no-excuse proposes clearly out-of-scope applications for human confirmation and answers immediately after approval, then analyses relevant profiles. Every application gets an overall score, criterion-by-criterion evidence and an explained summary; the top 10 supports the final human decision and never selects automatically. Offers and application catalogues are never public.

## Workflow

1. A recruiter creates a private campaign and receives a revocable ingestion key.
2. A low-cost AI queue proposes clearly out-of-scope applications; a recruiter confirms rejection or continues analysis.
3. A deeper analysis queue produces explainable scores for the remaining applications.
4. At closing time, recruiters review, annotate and reorder a top 10 before making the final human decision.
5. Every non-selected candidate receives a response, their score and optional recruiter feedback.

The application supports separate AI providers/models for screening and scoring, editable prompts under immutable fairness rules, 1–10 adaptive workers per queue, an MIT PDF reader, configurable CV and candidate-data retention, and an isolated public demo with capacity control. Demo PDF files are materialized once and shared read-only; lightweight Laravel jobs replay precomputed screening and scoring results without repeating PDF extraction or AI analysis for each visitor.

Although the demo sends no candidate email, recruiters can preview the exact production HTML response inside an isolated frame. Independent work on no-excuse and [Sonomundi](https://sonomundi.com) can be supported on [Ko-fi](https://ko-fi.com/axxon).

## Stack

PHP 8.5, Laravel 13, Vue 3, TypeScript 6, Vite 8, PostgreSQL 18, Redis 8, Docker Compose and Make.

## Start

Requirements: Docker, Docker Compose and Make. No host PHP or Node runtime is required.

```bash
make setup
```

Open `http://localhost:5173` for the interface and `http://localhost:18080/api` for the API. Run the complete Docker validation rail with `make validate`.

On `no-excuse.pro`, **Try the demo** automatically creates a temporary recruiter workspace with no credentials. The sandbox also exposes the detailed backoffice configuration in read-only mode; settings and team management remain locked. The login form is reserved for company installations and is not presented as the main path on the public domain.

## Integrations and operations

- [Generic ingestion API](docs/integration-api.en.md) and [OpenAPI contract](docs/openapi.yaml)
- [LinkedIn connection](docs/linkedin.en.md)
- [Email configuration](docs/email.en.md)
- [Rejected-CV retention](docs/data-retention.en.md)
- [Operations, health and backups](docs/operations.en.md)
- [Public demo deployment](docs/public-demo-deployment.en.md)
- [License, attribution and terms](docs/legal.en.md)
- [Project manifesto](MANIFESTO.md) and [third-party notices](THIRD_PARTY_NOTICES.md)

AI credentials are server-side secrets. They are never entered or returned through the recruiter interface. The public demo replays deterministic fixtures locally and makes no paid AI call.

## Security and fairness

No public route lists an offer or application. Ingestion and login tokens are hashed, CV access is organization-scoped, prompts exclude sensitive/discriminatory criteria, and final selection remains human. In live AI mode, CV text is sent to the configured provider; operators must establish an appropriate data-processing and retention policy.

## License

MIT — use it, adapt it, distribute it and build on it. Then actually ship it, and do it well. See the [manifesto](MANIFESTO.md), [legal notes](docs/legal.en.md) and [third-party notices](THIRD_PARTY_NOTICES.md).
