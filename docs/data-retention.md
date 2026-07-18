# Rétention des candidatures

[English](data-retention.en.md) · Français

Par défaut, le fichier CV d’une candidature rejetée est supprimé immédiatement après l’envoi réussi de la réponse (`NO_EXCUSE_REJECTED_CV_RETENTION_DAYS=0`). La fiche conserve le statut, les scores, les dates et les événements, dont `cv_deleted_by_retention`, mais le document et son nom sont effacés. Un CV sélectionné n’est pas concerné. La démo fictive conserve ses documents jusqu’à la suppression intégrale de sa sandbox.

Le scheduler exécute aussi quotidiennement `applications:apply-retention`, afin de rattraper toute purge différée. Une valeur positive conserve le CV ce nombre de jours après notification.

Cette trace minimale n’exonère pas l’exploitant de définir la durée de conservation des autres données personnelles, les finalités, l’information des candidats et le traitement des demandes d’effacement. La CNIL indique notamment qu’un vivier de candidats ne devrait en principe pas dépasser deux ans après le dernier contact, avec information et base légale appropriée ; son référentiel 2026 distingue aussi l’archivage probatoire. Références : [question CNIL sur le dossier candidat](https://www.cnil.fr/fr/cnil-direct/question/recrutement-un-employeur-peut-il-conserver-mon-dossier) et [référentiel des durées RH](https://www.cnil.fr/sites/default/files/2026-04/referentiel_durees_de_conservation_gestion_des_ressources_humaines.pdf).

Cette documentation n’est pas un avis juridique : adaptez la politique au pays, à la finalité et aux obligations de l’entreprise.
