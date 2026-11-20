# AGENTS.md — Engineering Rules for Cambodia Game Top-Up Platform

## Architecture Boundaries
- **Backend (`backend/`)** is a modular Laravel monolith with PostgreSQL as the primary source of truth.
- Never trust client-side prices, payment statuses, or order statuses.
- All financial amounts are integer minor units (`USD` = cents, `KHR` = integer Riel).
- Financial ledger (`LedgerService`) enforces `sum(debits) == sum(credits)` per transaction and currency.
- Never delete financial history via cascading deletes (`restrictOnDelete()` is enforced on all financial tables).

## Critical Invariants
1. **Zero fulfillment before verified payment** (`Order.payment_status === 'paid'`).
2. **Zero duplicate fulfillment on retries**: `Fulfillment.idempotency_key` is immutable per logical order fulfillment; uncertain/timeout deliveries must reconcile via supplier status lookup or escalate to `needs_review`.
3. **Guest order security**: Order tracking requires either authenticated ownership or the high-entropy 64-char secret `tracking_token` (stored as SHA-256 hash).
4. **Production safety guard**: `ProductionSafetyGuard` blocks `mock_payway` and `mock_supplier` in `production` unless `TOPUP_ALLOW_MOCKS_IN_PRODUCTION=true`.
5. **No secrets in frontend**: Never expose supplier keys, payment secrets, or webhook secrets in Nuxt `runtimeConfig.public`.

## Verification Commands
- Backend tests: `cd backend && php artisan test`
- Frontend unit tests: `pnpm test:frontend`
- Production frontend builds: `pnpm build`
