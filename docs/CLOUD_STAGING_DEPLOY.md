# VannTopUp — Cloud Staging Deployment Guide (GitHub + Render / Vercel)

## 1. Push Code to GitHub
Run these commands inside the project folder (`Website_TopUp`):

```powershell
git add .
git commit -m "feat: VannTopUp production platform ready for cloud staging"
git branch -M main
git remote add origin https://github.com/<YOUR_USERNAME>/<YOUR_REPO>.git
git push -u origin main
```

---

## 2. 1-Click Full-Stack Deploy on Render.com (Recommended — Free Tier)
This repository includes a complete [`render.yaml`](../render.yaml) Blueprint that deploys **all 4 services automatically**:
1. **`vanntopup-db`** — PostgreSQL 16 database
2. **`vanntopup-api`** — Laravel 13 API (`backend/Dockerfile`, auto-migrates & seeds on first boot)
3. **`vanntopup-storefront`** — Customer Nuxt 3 website
4. **`vanntopup-admin`** — Admin Operations Console

### Steps on Render:
1. Go to [https://dashboard.render.com](https://dashboard.render.com) and sign in with GitHub.
2. Click **New +** → **Blueprint**.
3. Select your `VannTopUp` GitHub repository and click **Apply**.
4. Wait ~3–5 minutes for the first build to complete.
5. Once live, verify your 3 URLs:
   - **Storefront:** `https://vanntopup-storefront.onrender.com`
   - **Admin Console:** `https://vanntopup-admin.onrender.com`
   - **API Health Check:** `https://vanntopup-api.onrender.com/api/v1/health`

> **Important Note on Service URLs:** If Render appends a random suffix to your API URL (e.g. `https://vanntopup-api-xxxx.onrender.com`), update `NUXT_PUBLIC_API_BASE` on `vanntopup-storefront` and `vanntopup-admin` to `https://<your-actual-api-host>/api/v1` and trigger a quick redeploy.

---

## 3. Optional: Deploy Storefront & Admin on Vercel (Ultra-Fast CDN)
If you want even faster page loads for the frontend, keep `vanntopup-api` + `vanntopup-db` on Render/Railway and import the same GitHub repo twice on **Vercel**:

- **Project 1 (Storefront):**
  - Root Directory: `apps/storefront`
  - Install Command: `cd ../.. && pnpm install --frozen-lockfile`
  - Build Command: `cd ../.. && pnpm build:storefront`
  - Env Var: `NUXT_PUBLIC_API_BASE=https://<your-api-url>/api/v1`

- **Project 2 (Admin Console):**
  - Root Directory: `apps/admin`
  - Install Command: `cd ../.. && pnpm install --frozen-lockfile`
  - Build Command: `cd ../.. && pnpm build:admin`
  - Env Var: `NUXT_PUBLIC_API_BASE=https://<your-api-url>/api/v1`

---

## 4. Seeded Staging Accounts
- **Super Admin (`/login` on Admin Console):** `admin@khmertopup.local` / `AdminPass123!`
- **Demo Customer (`/account` on Storefront):** `player@khmertopup.local` / `CustomerPass123!`

## 5. Moving to Real Production Later
When ready for real money:
1. Set `APP_ENV=production` on `vanntopup-api` (`ProductionSafetyGuard` automatically blocks mock payment & mock supplier adapters!).
2. Configure real `ABA_PAYWAY_*`, `KASPLAY_*` / `UNIPIN_*`, and `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` env vars.
3. Replace `CORS_ALLOWED_ORIGIN_PATTERNS` with your exact custom domains in `CORS_ALLOWED_ORIGINS`.
4. Set `RUN_SEED_ON_BOOT=false` after initial database setup.
