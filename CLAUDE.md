# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A recipe-finder PWA: users enter ingredients they have, and the app ranks recipes by how many of their ingredients match. When the local catalog is thin, it asks Google Gemini for more recipes, imports them, and re-runs the match. Laravel 13 + Livewire 4 (public UI) + Filament 5 (admin). PHP 8.3, PostgreSQL, Pest 4.

## Commands

```bash
composer dev          # run server + queue + pail logs + vite concurrently (main dev loop)
composer test         # clears config, then runs the full suite
php artisan test --filter=RecipeMatcherTest   # single test class
php artisan test tests/Feature/RecipeFinderTest.php   # single file
./vendor/bin/pint     # lint/format (Laravel Pint)
php artisan migrate --seed   # migrate + seed recipes & admin user
npm run dev / npm run build  # vite assets
```

**Tests require a real PostgreSQL database**, not sqlite. `phpunit.xml` points at a `recipe_pwa_test` DB on `127.0.0.1:5432` (user `postgres`). Create it before running tests, or the suite errors on connect.

## Architecture

**Ingredient matching is the core.** `RecipeMatcher::search()` (app/Services/Matching) takes raw ingredient strings, normalizes them, and scores every recipe as `matched / total ingredients`. Recipes scoring `>= threshold` (default 0.5) come back as `MatchResult` sorted by score. Matching is **exact-name on normalized strings** — there is no fuzzy/synonym matching.

**`IngredientNormalizer::normalize()` is the single join key for the whole domain.** Lowercase → trim → strip surrounding punctuation → collapse whitespace. Stored ingredient names, user input, AI imports, and dedup checks all pass through it. If you change normalization, existing rows won't match new input — treat it as a data-format change, not a cosmetic one.

**AI flow** (app/Services/Gemini): `RecipeFinder::exploreWithAi()` → `GeminiRecipeClient::suggest()` (calls Gemini for 3 JSON recipes, **cached 6h** keyed by sorted normalized ingredients) → `GeminiResponseParser` → `AiRecipeImporter::importMany()` (creates recipes with `source = ai`, dedups by normalized name, attaches ingredients via `Ingredient::findOrCreateNormalized`) → re-run match. Gemini config lives in `config/services.php` (`GEMINI_API_KEY`, `GEMINI_ENDPOINT`); if unset the client throws and the UI shows a generic error.

**Data model**: `Recipe` ↔ `Ingredient` many-to-many through `recipe_ingredient` (pivot has `quantity`). `Recipe.source` is `seed` or `ai`. `Rating` is anonymous per-session (no user FK) — see `RecipeRating` Livewire component.

**Frontend**: public pages are Livewire components (`RecipeFinder`, `RecipeRating`) under `app/Livewire`. Admin CRUD is Filament at `/admin` (`app/Filament/Resources/Recipes`), seeded admin from `ADMIN_EMAIL`/`ADMIN_PASSWORD` via `AdminSeeder`. PWA `manifest.json` and `sw.js` live in `public/` and are served through explicit routes in `routes/web.php`.

## Notes

- `RecipeFinder::search()` currently has a leftover `var_dump` (app/Livewire/RecipeFinder.php) — remove before shipping.
- Deploy config (Procfile, free-tier PaaS) and optional error monitoring are documented in `docs/DEPLOY.md`.
- Design spec and implementation plan: `docs/superpowers/`.
