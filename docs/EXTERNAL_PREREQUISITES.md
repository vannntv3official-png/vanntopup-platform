# External Prerequisites for Live Commercial Launch

This platform is **software-complete** for local development, staging, and automated testing. Before enabling live commercial transactions in Cambodia, the following external business and integration prerequisites MUST be completed:

1. **ABA PayWay Merchant Approval (Cambodia)**
   - Approved ABA Bank Cambodia merchant account with KHQR & PayWay API enabled.
   - Provision `ABA_PAYWAY_MERCHANT_ID`, `ABA_PAYWAY_API_KEY`, `ABA_PAYWAY_PUBLIC_KEY`, and `ABA_PAYWAY_WEBHOOK_SECRET`.
   - Validate `AbaPayWayAdapter` against ABA PayWay Sandbox (`https://checkout-sandbox.payway.com.kh`) for QR generation, webhook signature verification, `check-transaction-2` verification, and refund capabilities.

2. **Authorized Game Top-Up Supplier Accounts**
   - **KAS Play:** Executed reseller agreement, API key/secret, IP allowlist, verified product SKU list for Mobile Legends, Free Fire, PUBG Mobile, and Honor of Kings, and sandbox test fulfillment.
   - **UniPin:** Approved partner account, Partner ID, Secret Key, and sandbox certification.

3. **Infrastructure & Observability Secrets**
   - Production PostgreSQL 16 + Redis 7 managed instances or hardened hosts.
   - Cloudflare DNS/WAF + TLS certificates.
   - S3-compatible object storage bucket for encrypted database backups.
   - Sentry DSN (`SENTRY_LARAVEL_DSN`) and/or OpenTelemetry collector endpoint.
   - Transactional Email (SMTP/SES/Postmark) and authorized Telegram Bot token if Telegram notifications are enabled.

4. **Legal & Compliance Review**
   - Cambodia e-commerce licensing, tax invoicing, privacy policy, and refund policy legal review.
