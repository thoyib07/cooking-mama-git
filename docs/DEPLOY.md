# Deploy Guide — Recipe PWA

## Prerequisites
- Neon PostgreSQL account (free tier): https://neon.tech
- PaaS account — **Koyeb** is recommended if you don't have a credit card (see below);
  Render/Railway/Heroku are alternatives if you do
- Gemini API key: https://aistudio.google.com

## Steps

### 1. Create Neon Database
1. Sign up at neon.tech and create a new project
2. Copy the `DATABASE_URL` connection string (postgres://...)
3. Note: Neon free tier sleeps after 5 min inactivity — first request after sleep takes ~2-3 seconds

### 2. Deploy to PaaS

**Koyeb (recommended — no credit card required):**
1. Sign up at koyeb.com (GitHub login is fastest) and click **Create Web Service**
2. Choose **GitHub**, install the Koyeb GitHub App if prompted, select this repo
3. **Branch**: the branch you want to deploy (e.g. `deploy-main`)
4. **Builder**: select `Dockerfile` (path defaults to `./Dockerfile`, no change needed)
5. **Exposed ports**: set `10000` with protocol `http` (matches the `Dockerfile`'s
   default `PORT`; the container reads `$PORT` if Koyeb injects a different one)
6. **Environment variables**: add all variables from the table below
7. **Instance type**: `Free` — **Region**: Frankfurt or Washington, D.C. (only options
   on the free instance)
8. Click **Deploy**. The container runs `composer install`, asset build, `migrate
   --force`, `db:seed --class=AdminSeeder --force`, then serves on `$PORT` — all on
   every container start (idempotent, safe to re-run each redeploy)

**Render:**
Render no longer offers a native PHP runtime for new Web Services, and free Web
Services have been inconsistently asking for a credit card. If you have one and want
to try anyway: deploy with the `Dockerfile` at the repo root, Language `Docker`,
Instance Type `Free` — same idea as Koyeb above, minus the port field (Render injects
`$PORT` automatically).

**Railway/Heroku:** Use the `Procfile` at the repo root (native PHP buildpack, no Docker needed) — both currently require a card on file.

### 3. Set Environment Variables

Copy from `.env.production.example` and fill in:

| Variable | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | Your service's public URL (e.g. `https://your-app.koyeb.app`) |
| `APP_KEY` | Run `php artisan key:generate --show` locally |
| `DB_CONNECTION` | `pgsql` — required, defaults to `sqlite` otherwise even with `DATABASE_URL` set |
| `DATABASE_URL` | From Neon dashboard |
| `GEMINI_API_KEY` | From Google AI Studio |
| `GEMINI_ENDPOINT` | `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent` |
| `GROQ_API_KEY` | From console.groq.com — used as AI fallback when Gemini rate-limits |
| `ADMIN_EMAIL` | Your admin email |
| `ADMIN_PASSWORD` | A strong password |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |

### 4. Verify Deploy
- Visit `/` — recipe finder should load
- Visit `/admin` — login with admin credentials
- Visit `/manifest.json` — should return valid JSON
- Test "Eksplor dengan AI" with a few ingredients

## Monitoring (optional, free tier)

Install Sentry: `composer require sentry/sentry-laravel`
Then set `SENTRY_DSN` from your Sentry project. If `SENTRY_DSN` is not set, the app runs normally without monitoring — rely on PaaS logs instead.

## Free-Tier Limits to Watch

- **Neon**: 512MB storage, compute sleeps after 5 min idle
- **Koyeb free**: 512MB RAM / 0.1 vCPU, one free instance per account, scales to zero after 1h idle (cold start on next request), only Frankfurt or Washington D.C. regions; local filesystem is ephemeral, so recipe images uploaded via `/admin` are lost on every redeploy/restart unless moved to external storage (S3, Cloudinary, etc.)
- **Render free**: Spins down after 15 min inactivity (30s cold start); same ephemeral-filesystem caveat as above; has been inconsistently requiring a credit card even on the free tier
- **Gemini API**: 15 requests/minute on free tier; results are cached 6 hours per unique ingredient set
- **Groq API**: has its own free-tier rate limit; only called when Gemini fails
