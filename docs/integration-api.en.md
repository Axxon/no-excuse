# Ingestion API

English · [Français](integration-api.md)

The ingestion API connects an external source to a private no-excuse catalogue. It cannot list an offer or read an application.

## Credentials and request

The recruiter creates a campaign and copies its one-time ingestion key. A rotation immediately revokes the previous key.

```bash
curl --request POST 'https://no-excuse.example/api/v1/intake/OFFER_UUID/applications' \
  --header 'Authorization: Bearer INGESTION_KEY' \
  --header 'Accept: application/json' \
  --form 'source=linkedin' \
  --form 'external_reference=linkedin-application-1234' \
  --form 'candidate_name=Ada Martin' \
  --form 'candidate_email=ada@example.test' \
  --form 'cover_letter=Optional message' \
  --form 'cv=@/path/to/cv.pdf;type=application/pdf'
```

Required fields are `source` (max 80), `external_reference` (max 190), `candidate_name` (max 160), a valid `candidate_email`, and an unencrypted PDF-only `cv` (`application/pdf`) up to 10 MiB and 100 pages. `cover_letter` is optional (max 10,000 characters).

HTTP 202 returns `application_reference`, `status: accepted`, and `duplicate: false`. The pair `source + external_reference` is idempotent within an offer; a duplicate returns HTTP 200 without creating another application. Screening does not decide on its own: an apparently out-of-scope application becomes **Rejection to confirm** with a factual explanation. A recruiter confirms rejection or continues deeper analysis. Confirmation immediately queues the response; the card then shows pending, sending, sent, or needs-attention mail status.

A campaign remains **Closing** while analysis, human confirmations or retries are outstanding. The top 10 is built only after every application has stabilized. Scoring compares professional evidence against announced criteria and never performs final selection.

Connectors should retain the returned reference and retry only network/5xx failures with exponential backoff. Store the key in a secret manager, transmit it only over HTTPS, never log keys or application contents, and use one key per campaign. `404` means unknown URL/key, `409` a closed campaign, `422` invalid input and `429` rate limiting.

For LinkedIn, do not scrape pages; follow the [LinkedIn guide](linkedin.en.md).
