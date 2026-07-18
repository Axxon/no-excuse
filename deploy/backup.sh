#!/bin/sh
set -eu

if [ "$#" -lt 1 ]; then
    echo "Usage: deploy/backup.sh DESTINATION [docker compose options...]" >&2
    exit 2
fi

destination=$1
shift
umask 077
mkdir -p "$destination"

docker compose "$@" exec -T postgres sh -c 'exec pg_dump --format=custom --no-owner --username="$POSTGRES_USER" "$POSTGRES_DB"' > "$destination/database.dump"
docker compose "$@" exec -T api tar -C /app/storage -czf - app > "$destination/storage.tar.gz"
sha256sum "$destination/database.dump" "$destination/storage.tar.gz" > "$destination/SHA256SUMS"

echo "Backup created in $destination"
