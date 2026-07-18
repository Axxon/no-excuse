#!/bin/sh
set -eu

secret_directory="${XDG_CONFIG_HOME:-$HOME/.config}/no-excuse"
secret_file="${secret_directory}/mailer.env"

umask 077
mkdir -p "$secret_directory"
chmod 700 "$secret_directory"

printf 'Identifiant SMTP Brevo : ' >&2
IFS= read -r smtp_username

printf 'Collez la nouvelle clé SMTP Brevo (la saisie restera invisible) : ' >&2
stty -echo
trap 'stty echo' EXIT HUP INT TERM
IFS= read -r smtp_key
stty echo
trap - EXIT HUP INT TERM
printf '\n' >&2

printf 'Adresse expéditrice authentifiée : ' >&2
IFS= read -r from_address
printf 'Nom expéditeur [no-excuse] : ' >&2
IFS= read -r from_name
from_name="${from_name:-no-excuse}"

if [ -z "$smtp_username" ] || [ -z "$smtp_key" ] || [ -z "$from_address" ]; then
    echo 'Identifiant, clé ou adresse expéditrice manquant. Aucun fichier modifié.' >&2
    exit 1
fi

{
    printf '%s\n' \
        'MAIL_MAILER=smtp' \
        'MAIL_SCHEME=smtp' \
        'MAIL_HOST=smtp-relay.brevo.com' \
        'MAIL_PORT=587'
    printf 'MAIL_USERNAME=%s\n' "$smtp_username"
    printf 'MAIL_PASSWORD=%s\n' "$smtp_key"
    printf 'MAIL_FROM_ADDRESS=%s\n' "$from_address"
    printf 'MAIL_FROM_NAME=%s\n' "$from_name"
} > "$secret_file"

unset smtp_username smtp_key from_address from_name
chmod 600 "$secret_file"
echo 'Clé SMTP enregistrée de manière protégée.'
