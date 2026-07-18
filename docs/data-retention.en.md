# Application retention

English · [Français](data-retention.md)

By default, a rejected application’s CV file is deleted immediately after its response is successfully sent (`NO_EXCUSE_REJECTED_CV_RETENTION_DAYS=0`). The record keeps status, scores, timestamps and audit events including `cv_deleted_by_retention`; the file and original filename are removed. The company also configures selected-CV retention and full candidate anonymization in the interface. Fictional demo files remain until the complete sandbox expires.

The scheduler runs `applications:apply-retention` daily. A positive rejected-CV value retains the file for that many days after notification. At the full-data deadline, name, email, cover letter, annotations, AI textual explanations, scoring breakdown, errors and the file are erased; only status, numeric scores, timestamps and minimal events remain. An authorized recruiter can export a record or trigger erasure after the final decision.

Waitlist registrations are removed no later than `NO_EXCUSE_WAITLIST_RETENTION_DAYS`, or seven days after notification. Addresses are encrypted and the deduplication index is an `APP_KEY`-keyed HMAC.

Operators must separately define retention for remaining personal data, purposes, candidate notices and erasure requests. CNIL guidance generally limits candidate pools to two years after last contact under appropriate information/legal basis and distinguishes evidentiary archives. See the [CNIL candidate-file answer](https://www.cnil.fr/fr/cnil-direct/question/recrutement-un-employeur-peut-il-conserver-mon-dossier) and [2026 HR retention reference](https://www.cnil.fr/sites/default/files/2026-04/referentiel_durees_de_conservation_gestion_des_ressources_humaines.pdf). This is not legal advice.
