# Cambodia Game Top-Up Platform (`KhmerTopUp`)

Production-grade automatic game currency top-up e-commerce platform targeting customers in Cambodia, supporting **Khmer (default)** and **English**, **USD** and **KHR**, KHQR/PayWay payments, resilient automated supplier fulfillment, balanced double-entry financial ledger, and a protected RBAC admin console.

## Launch Games
1. **Mobile Legends: Bang Bang** (User ID + Zone ID, KH & Global)
2. **Garena Free Fire** (Player ID, KH/SEA)
3. **PUBG Mobile** (Character ID, Global)
4. **Honor of Kings** (Player UID, Global/SEA)

## Monorepo Layout
- `apps/storefront` — Nuxt 3 + Vue 3 + TypeScript + Tailwind CSS + Pinia + Vue I18n (`km`/`en`) customer website
- `apps/admin` — Nuxt 3 + Vue 3 + TypeScript + Tailwind CSS protected Operations Admin Dashboard
- `backend` — Laravel 13 REST API + Sanctum + PostgreSQL/SQLite + Redis queues + PHPUnit test suite
- `packages/shared-types` — Shared TypeScript types, enums, and USD/KHR minor-unit formatting
- `packages/api-contracts` — Zod validation schemas & OpenAPI 3.1 spec (`openapi.yaml`)
- `packages/design-tokens` — Dark gaming commerce color palette, Kantumruy Pro / Inter typography, Tailwind preset
- `infrastructure/` — Docker Compose, Dockerfiles, Nginx reverse proxy, Prometheus config
- `docs/` — Architecture, Security, Deployment, External Prerequisites, and Operational Runbooks
- `.github/workflows/ci.yml` — Automated CI/CD (Backend PostgreSQL tests, Frontend unit tests & builds, secret scanning)

## Quick Start (Local Development)

### Prerequisites
- PHP 8.3+, Composer 2.x
- Node.js 22+, pnpm 12+
- Optional: Docker & Docker Compose for full PostgreSQL + Redis + Nginx stack

### 1. Run automated local setup
```powershell
.\scripts\setup-local.ps1
```

### 2. Start services locally
```bash
# Terminal 1 — Laravel API (http://127.0.0.1:8000)
cd backend && php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2 — Customer Storefront (http://localhost:3000)
pnpm dev:storefront

# Terminal 3 — Operations Admin Console (http://localhost:3001)
pnpm dev:admin
```

### 3. Docker Compose (PostgreSQL + Redis + API + Worker + Scheduler + Storefront + Admin + Nginx)
```bash
docker compose -f infrastructure/docker/docker-compose.yml up --build
```

## Seeded Local Development Accounts
- **Super Admin:** `admin@khmertopup.local` / `AdminPass123!`
- **Customer:** `player@khmertopup.local` / `CustomerPass123!`
- **Promo Code:** `KHMER10` (10% off up to \$2.00)

## Deterministic Mock Supplier Triggers (Local/Testing Only)
Pass these values in the Player ID / User ID field during local testing:
- Any normal numeric ID (e.g. `123456789`) → Instant deterministic success
- `999999999` → Definitive supplier failure (safe for admin retry or refund)
- `888888888` → Supplier timeout (`uncertain`) → auto-reconciled to `completed` via status lookup
- `666666666` → Unresolved timeout → escalates safely to `needs_review` + discrepancy record

## Documentation
- [Architecture](docs/ARCHITECTURE.md)
- [Security Model](docs/SECURITY.md)
- [Deployment & Backups](docs/DEPLOYMENT.md)
- [Operational Runbooks](docs/runbooks/OPERATIONAL_RUNBOOKS.md)
- [External Prerequisites for Live Launch](docs/EXTERNAL_PREREQUISITES.md)
- [OpenAPI 3.1 Specification](packages/api-contracts/openapi.yaml)
