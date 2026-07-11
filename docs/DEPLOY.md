# Deploy Guide — Recipe PWA

## Prerequisites
- Neon PostgreSQL account (free tier): https://neon.tech
- PaaS account (Render, Railway, or Heroku — all have free/hobby tiers)
- Gemini API key: https://aistudio.google.com

## Steps

### 1. Create Neon Database
1. Sign up at neon.tech and create a new project
2. Copy the `DATABASE_URL` connection string (postgres://...)
3. Note: Neon free tier sleeps after 5 min inactivity — first request after sleep takes ~2-3 seconds

### 2. Deploy to PaaS

**Render:**
Render no longer offers a native PHP runtime for new Web Services — deploy with the
`Dockerfile` at the repo root instead:
1. Create a new "Web Service" from this GitHub repo
2. Language: `Docker` (Dockerfile Path defaults to `./Dockerfile`, no change needed)
3. Branch: the branch you want to deploy (e.g. `deploy-main`)
4. No separate build/start command needed — the Dockerfile handles asset build,
   `composer install`, `migrate --force`, `db:seed --class=AdminSeeder --force`, and
   starting the server on Render's `$PORT` automatically on every container start
   (migrate/seed are idempotent, safe to re-run).
5. Instance Type: `Free`

**Railway/Heroku:** Use the `Procfile` at the repo root (native PHP buildpack, no Docker needed).

### 3. Set Environment Variables

Copy from `.env.production.example` and fill in:

| Variable | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | Your Render service URL (e.g. `https://your-app.onrender.com`) |
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
- **Render free**: Spins down after 15 min inactivity (30s cold start); local filesystem is ephemeral, so recipe images uploaded via `/admin` are lost on every redeploy/restart unless moved to external storage (S3, Cloudinary, etc.)
- **Gemini API**: 15 requests/minute on free tier; results are cached 6 hours per unique ingredient set
- **Groq API**: has its own free-tier rate limit; only called when Gemini fails
