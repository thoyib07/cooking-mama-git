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
1. Create a new "Web Service" from this GitHub repo
2. Build command: `composer install --no-dev && php artisan config:cache`
3. Start command: `vendor/bin/heroku-php-apache2 public/`
4. Add release command (Pre-deploy): `php artisan migrate --force && php artisan db:seed --class=AdminSeeder --force`

**Railway/Heroku:** Use the `Procfile` at the repo root.

### 3. Set Environment Variables

Copy from `.env.production.example` and fill in:

| Variable | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Run `php artisan key:generate --show` locally |
| `DATABASE_URL` | From Neon dashboard |
| `GEMINI_API_KEY` | From Google AI Studio |
| `GEMINI_ENDPOINT` | `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent` |
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
- **Render free**: Spins down after 15 min inactivity (30s cold start)
- **Gemini API**: 15 requests/minute on free tier; results are cached 6 hours per unique ingredient set
