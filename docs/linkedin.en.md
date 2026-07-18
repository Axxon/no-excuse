# Connect LinkedIn

English · [Français](linkedin.md)

no-excuse is a private downstream receiver. It does not scrape LinkedIn and exposes neither jobs nor application tracking.

Recommended flow: `Candidate → LinkedIn Apply → certified ATS/connector → no-excuse API`.

Create the campaign in no-excuse, give its ingestion URL and Bearer key to the ATS/middleware, then map the received application to the [multipart ingestion contract](integration-api.en.md) using `source=linkedin` and the LinkedIn identifier as `external_reference`. Retain the returned reference and retry only network/5xx failures.

LinkedIn **Apply Connect** is an official LinkedIn-to-partner-ATS integration. Its job payload supports a `jobApplicationWebhookUrl`, but production access requires LinkedIn permissions and partner certification; it is not a universal webhook for arbitrary installations. See the official [Apply Connect overview](https://learn.microsoft.com/en-us/linkedin/talent/apply-connect/apply-connect-overview), [job/webhook setup](https://learn.microsoft.com/en-us/linkedin/talent/apply-connect/create-apply-connect-jobs), and [certification](https://learn.microsoft.com/en-us/linkedin/talent/apply-connect/apply-connect-certification).

Without partner access, use an existing LinkedIn-connected ATS or send candidates to the company career form, which then relays the application. Never scrape LinkedIn or reuse a browser session cookie.
