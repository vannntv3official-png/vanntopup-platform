# System Architecture — Cambodia Game Top-Up Platform

## High-Level Architecture
Modular Laravel monolith (`backend/`) backed by PostgreSQL (primary source of truth) and Redis (queues, locks, cache), serving two decoupled Nuxt 3 applications (`apps/storefront` and `apps/admin`) behind an Nginx same-site reverse proxy.

## Core Domain Modules
1. **Identity & RBAC:** UUID users, `roles`, `permissions`, `role_permissions`, `user_roles`, `admin_sessions`, RFC 6238 TOTP MFA (`MfaService`), Sanctum API tokens.
2. **Game Catalog:** Dynamic `categories`, `games`, `game_localizations` (`km`/`en`), `game_assets`, `game_regions`, `game_fields` (regex-validated player inputs), `game_packages`, `package_regions`.
3. **Pricing & Quotation:** `PricingService` (bcmath USD↔KHR minor-unit conversion, minimum profit floor, coupon calculation, payment fee bps) and `PriceQuoteService` (HMAC-SHA256 signed, expiring, single-use quotes).
4. **Order Management:** `OrderService` + `OrderStateMachine` with encrypted `player_fields_encrypted`, SHA-256 hashed 64-char secret `tracking_token`, idempotent order creation, immutable `order_status_history`.
5. **Payment Gateway:** `PaymentGatewayInterface` with `MockPayWayAdapter` (dev/test) and `AbaPayWayAdapter` (ABA PayWay KHQR). `PaymentService` enforces webhook signature verification, event deduplication, server-to-server verification, amount/currency matching, safe late-payment review, and balanced ledger posting.
6. **Automatic Fulfillment Engine:** `FulfillmentOrchestrator`, `SupplierRouter`, `FulfillmentStateMachine`, `FulfillmentAuditService`, `SupplierHealthMonitor`, `SupplierBalanceMonitor`, `SubmitFulfillmentJob` (`ShouldBeUnique`), `ReconcileFulfillmentJob` (`ShouldBeUnique`), `TopUpSupplierInterface` (`MockSupplierAdapter`, `KasPlayAdapter`, `UniPinAdapter`), and transactional `outbox_events`.
7. **Double-Entry Financial Ledger:** `LedgerService` enforces strict `sum(debits) == sum(credits)` per transaction and single currency (separate USD and KHR chart of accounts), append-only, idempotent.
8. **Reconciliation & Operations:** `ReconciliationService`, `ReconciliationDiscrepancy` (immutable audit history), `NotificationService`, `SupportTicket`/`SupportMessage`, `AuditLog`, `SystemSetting` (including global fulfillment pause switch).
