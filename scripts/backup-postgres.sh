#!/usr/bin/env bash
set -euo pipefail

TIMESTAMP=$(date -u +"%Y%m%dT%H%M%SZ")
BACKUP_DIR="${BACKUP_DIR:-./backups}"
mkdir -p "${BACKUP_DIR}"
FILE="${BACKUP_DIR}/topup_${TIMESTAMP}.sql.gz.gpg"

: "${PGHOST:?PGHOST required}"
: "${PGDATABASE:?PGDATABASE required}"
: "${PGUSER:?PGUSER required}"
: "${BACKUP_GPG_RECIPIENT:?BACKUP_GPG_RECIPIENT required}"

pg_dump --format=plain --no-owner --no-privileges \
  | gzip -9 \
  | gpg --batch --yes --encrypt --recipient "${BACKUP_GPG_RECIPIENT}" --output "${FILE}"

echo "Encrypted backup created: ${FILE}"
