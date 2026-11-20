# Security Configuration Guide

## Authentication, Sessions, CORS & CSRF
- **Same-Site Deployment:** Nginx routes `/`, `/admin`, and `/api/` under one origin so cookies and API calls remain same-site.
- **API Auth:** Laravel Sanctum bearer tokens with per-route RBAC middleware (`permission:<slug>`).
- **Admin MFA:** Privileged admin accounts support RFC 6238 TOTP MFA (`POST /api/v1/auth/mfa/setup`, `POST /api/v1/auth/mfa/confirm`, enforced on login when enabled).
- **Password Hashing:** Strong password hashing (`Hash::make`, configurable to `argon2id` via `HASH_DRIVER=argon2id`).
- **Rate Limiting:** Auth endpoints (`10/min`), quotes (`60/min`), orders (`30/min`), webhooks (`120/min`), plus Nginx `limit_req_zone`.

## Financial & Order Integrity
- **Server-Trusted Quotes:** Every order requires an unconsumed, non-expired, HMAC-SHA256 signed `PriceQuote`. Client-submitted totals are never accepted.
- **Guest Order Ownership:** Guest tracking requires the high-entropy 64-char hex `tracking_token` (stored as SHA-256 hash). Sequential order numbers alone return `403 Forbidden`.
- **Payment Integrity:** Webhooks require valid cryptographic signatures, deduplication by `external_event_id`, server-to-server status verification, and exact amount/currency match before marking paid.
- **Production Safety Guard:** `ProductionSafetyGuard` throws a fatal exception if `mock_payway` or `mock_supplier` is invoked in `APP_ENV=production` unless `TOPUP_ALLOW_MOCKS_IN_PRODUCTION=true`.
- **Data Protection:** `player_fields_encrypted`, `mfa_secret_encrypted`, `telegram_chat_id_encrypted`, and `supplier_credentials.credentials_encrypted` are encrypted at rest via Laravel's application encryption cipher. `RedactionService` strips sensitive keys before audit/attempt logging.
