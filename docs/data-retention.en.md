# Application retention

English · [Français](data-retention.md)

By default, a rejected application’s CV file is deleted immediately after its response is successfully sent (`NO_EXCUSE_REJECTED_CV_RETENTION_DAYS=0`). The record keeps status, scores, timestamps and audit events including `cv_deleted_by_retention`; the file and original filename are removed. Selected CVs are unaffected. Fictional demo files remain until the complete sandbox expires.

The scheduler also runs `applications:apply-retention` daily to catch deferred purges. A positive value retains the file for that many days after notification.

Operators must separately define retention for remaining personal data, purposes, candidate notices and erasure requests. CNIL guidance generally limits candidate pools to two years after last contact under appropriate information/legal basis and distinguishes evidentiary archives. See the [CNIL candidate-file answer](https://www.cnil.fr/fr/cnil-direct/question/recrutement-un-employeur-peut-il-conserver-mon-dossier) and [2026 HR retention reference](https://www.cnil.fr/sites/default/files/2026-04/referentiel_durees_de_conservation_gestion_des_ressources_humaines.pdf). This is not legal advice.
