# Rétention des candidatures

[English](data-retention.en.md) · Français

Par défaut, le fichier CV d’une candidature rejetée est supprimé immédiatement après l’envoi réussi de la réponse (`NO_EXCUSE_REJECTED_CV_RETENTION_DAYS=0`). La fiche conserve le statut, les scores, les dates et les événements, dont `cv_deleted_by_retention`, mais le document et son nom sont effacés. L’entreprise configure aussi dans l’interface la durée du CV sélectionné et le délai d’anonymisation complète des candidats. La démo fictive conserve ses documents jusqu’à la suppression intégrale de sa sandbox.

Le scheduler exécute quotidiennement `applications:apply-retention`. Une valeur positive conserve le CV rejeté ce nombre de jours après notification. À l’échéance globale, le nom, l’e-mail, la lettre, les annotations, les explications textuelles IA, le détail du scoring, les erreurs et le fichier sont effacés ; seuls les statuts, scores numériques, dates et événements minimaux restent. Un responsable peut exporter les données d’une fiche ou déclencher cet effacement depuis la campagne après la décision finale.

Les inscriptions de liste d’attente sont également supprimées : au plus tard selon `NO_EXCUSE_WAITLIST_RETENTION_DAYS`, ou sept jours après notification. L’adresse est chiffrée et son index de déduplication utilise un HMAC lié à `APP_KEY`.

Cette trace minimale n’exonère pas l’exploitant de définir la durée de conservation des autres données personnelles, les finalités, l’information des candidats et le traitement des demandes d’effacement. La CNIL indique notamment qu’un vivier de candidats ne devrait en principe pas dépasser deux ans après le dernier contact, avec information et base légale appropriée ; son référentiel 2026 distingue aussi l’archivage probatoire. Références : [question CNIL sur le dossier candidat](https://www.cnil.fr/fr/cnil-direct/question/recrutement-un-employeur-peut-il-conserver-mon-dossier) et [référentiel des durées RH](https://www.cnil.fr/sites/default/files/2026-04/referentiel_durees_de_conservation_gestion_des_ressources_humaines.pdf).

Cette documentation n’est pas un avis juridique : adaptez la politique au pays, à la finalité et aux obligations de l’entreprise.
