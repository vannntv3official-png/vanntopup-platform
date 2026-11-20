#!/usr/bin/env bash
set -euo pipefail

BACKUP_FILE="${1:?Usage: restore-postgres.sh <encrypted_backup.sql.gz.gpg>}"
: "${PGHOST:?PGHOST required}"
: "${PGDATABASE:?PGDATABASE required}"
: "${PGUSER:?PGUSER required}"

echo "Restoring encrypted backup ${BACKUP_FILE} into ${PGDATABASE} on ${PGHOST}..."
gpg --batch --yes --decrypt "${BACKUP_FILE}" | gunzip | psql --set ON_ERROR_STOP=1
echo "Database restore complete."
