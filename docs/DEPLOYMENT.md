# Production Deployment & Backup Guide

## Containerized Production Topology
Managed via `infrastructure/docker/docker-compose.yml`:
- `nginx` — Reverse proxy, TLS termination (behind Cloudflare), security headers, rate limits.
- `storefront` — Nuxt 3 SSR Node server (`:3000`).
- `admin` — Nuxt 3 Admin Console (`:3001`).
- `api` — Laravel 13 REST API (`:8000`).
- `worker` — Laravel Redis queue worker (`--queue=fulfillment,payments,notifications,default`, `stop_grace_period: 60s`).
- `scheduler` — Laravel scheduler for automated reconciliation, health checks, and cleanup.
- `postgres` — PostgreSQL 16 with persistent volume.
- `redis` — Redis 7 with AOF persistence (`--appendonly yes --maxmemory-policy noeviction`).

## Safe Rolling Deployment Procedure
1. Put workers into graceful drain (`php artisan queue:restart`) so in-flight fulfillment jobs finish cleanly without double processing.
2. Run database migrations safely (`php artisan migrate --force`).
3. Deploy new `api`, `worker`, `scheduler`, `storefront`, and `admin` images.
4. Verify `/api/v1/health` returns HTTP 200 with `database: connected`.

## Encrypted Automated Backups & Restore
- Backup script: `scripts/backup-postgres.sh` (pg_dump → gzip → GPG encryption → upload to S3-compatible object storage).
- Restore script: `scripts/restore-postgres.sh <encrypted_backup.sql.gz.gpg>`.
- Recommended retention: Daily encrypted backups retained 30 days; monthly financial archives retained 7 years (immutable).
