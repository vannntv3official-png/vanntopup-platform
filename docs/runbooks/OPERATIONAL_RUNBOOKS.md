# Operational & Incident Recovery Runbooks

## 1. Payment Gateway Outage (ABA PayWay)
- **Symptoms:** Elevated 5xx on `/orders/{orderNumber}/payment-sessions` or delayed webhooks.
- **Actions:**
  1. Check ABA PayWay status and API logs.
  2. Do NOT manually mark orders as paid without verified bank/merchant portal settlement evidence.
  3. Once gateway recovers, run `POST /api/v1/admin/reconciliation/run` to detect any paid-but-unfulfilled orders.

## 2. Supplier Outage (KAS Play / UniPin)
- **Symptoms:** Supplier health checks failing, orders escalating to `needs_review`.
- **Actions:**
  1. Pause affected supplier in Admin Console (`/suppliers` → Pause).
  2. If a healthy authorized fallback supplier exists, `SupplierRouter` automatically routes new orders to it.
  3. If no healthy supplier is available, enable **Emergency Global Fulfillment Pause** in Admin Overview.
  4. Never retry `uncertain` fulfillments with a different supplier until status lookup confirms non-execution.

## 3. Reconciliation Backlog
- **Actions:**
  1. Open Admin Console → `/reconciliation`.
  2. Prioritize `critical` discrepancies (`fulfilled_payment_mismatch`, `refund_after_fulfillment`), then `high` (`paid_not_fulfilled`).
  3. Cross-check provider transaction IDs and record resolution notes (immutable audit trail).

## 4. Queue Worker Failure / Crash
- **Actions:**
  1. Inspect Redis queue depth and failed jobs (`php artisan queue:failed`).
  2. Restart worker containers cleanly (`docker compose restart worker`).
  3. Because `Fulfillment.idempotency_key` and `SubmitFulfillmentJob::uniqueId()` are deterministic, restarted workers will not duplicate already-completed fulfillments; any already-submitted in-flight fulfillment automatically routes to `ReconcileFulfillmentJob`.

## 5. Database Restoration
- **Actions:**
  1. Enable Emergency Global Fulfillment Pause and stop `api`/`worker` containers.
  2. Run `scripts/restore-postgres.sh <latest-encrypted-backup.sql.gz.gpg>`.
  3. Verify `ledger_transactions` balance integrity and run reconciliation scan before resuming traffic.

## 6. Emergency Fulfillment Pause
- **Trigger:** Suspected supplier compromise, pricing bug, or runaway failure rate.
- **Actions:** Click **Emergency Pause Fulfillment** on Admin Overview (or `PATCH /api/v1/admin/settings` with `{"fulfillment_global_pause": true}`). Paid orders will safely enter `needs_review` instead of submitting to suppliers.

## 7. Credential Rotation
- **Actions:**
  1. Generate new API key/secret in ABA PayWay or supplier portal.
  2. Update encrypted secret store / `supplier_credentials` table and record `rotated_at`.
  3. Perform rolling restart of `api` and `worker` services; verify health checks; revoke old credentials.

## 8. Refund Incidents
- **Actions:**
  1. Verify order payment status (`paid`) and confirm fulfillment did NOT succeed.
  2. Trigger refund via Admin Orders (`/orders` → Issue Refund) with mandatory reason.
  3. If refund is requested after confirmed fulfillment, a `critical` `refund_after_fulfillment` discrepancy is automatically logged for finance sign-off.
