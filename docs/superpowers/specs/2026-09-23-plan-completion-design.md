# Plan Completion Design: Redis-Ready Cache, Extension Distribution Route, Housekeeping

**Date:** 2026-09-23
**Status:** Approved
**Related:** `plan.md` Task 18 Step 3 (Distribution), Task 20 Step 1 (Redis cache)

## Goal

Close the last two open items from `plan.md` Phase 3 and land the outstanding role-gating frontend work:

1. Commit the role-gating frontend fixes and clean Playwright artifacts.
2. Make the codebase Redis-ready for license validation caching without flipping the cache store (no Redis server exists on this machine).
3. Add the purchaser-facing browser extension distribution route (plan Task 18, Step 3).

## Decisions (user-confirmed)

- **Redis: Option A — code-ready, do not flip the switch.** Install `predis/predis`, keep `CACHE_STORE=database`, document the one-line flip for when a server exists. No runtime risk.
- **Extension route: Option A — purchaser distribution route.** Authenticated purchasers only, validated against the tool's configured browsers.

## 1. Housekeeping Commit (first)

Get a clean tree before feature work.

- Delete the 7 untracked `.playwright-mcp/page-*.yml` artifacts.
- Add `/.playwright-mcp/` to `.gitignore` so snapshots never come back.
- Commit the 6 modified frontend files with message: `feat: gate admin dashboard sections by role`.
  - Files: `App.jsx`, `Layout.jsx`, `ProtectedRoute.jsx`, `ToolTable.jsx`, `Dashboard.jsx`, `Tools.jsx`.

## 2. Redis-Ready Caching (Option A)

The app already routes all cache usage through the default store (`LicenseController::validateRequest` via `Cache::remember`, `LicenseCache` bust keys via `Cache::get`/`Cache::increment`). Wiring Redis therefore requires only dependency + env readiness.

**Changes:**

- `composer require predis/predis` — pure-PHP Redis client; no phpredis extension required (this is a Windows dev machine without the extension).
- `.env` and `.env.example`:
  - `REDIS_CLIENT=predis` (replaces `phpredis`, which is not installed).
  - Keep `CACHE_STORE=database`.
  - Add a commented line `# CACHE_STORE=redis` with a short note: flip this after starting a Redis server (the only action needed to move license validation to Redis).
- No application code changes.

**Why this completes the plan item:** Task 20 Step 1 asks for "Redis cache for license validation results." The caching layer itself is already built and tested (`PerfSecurityTest::test_license_validation_result_is_cached` and cache-bust tests). What was missing was the Redis client and configuration. With `predis` installed and env wired, enabling Redis is a one-line change plus a running server.

**Non-goal:** Flipping `CACHE_STORE` to `redis` now. No Redis server exists (port 6379 closed, no Docker, no redis-cli). Flipping it would 500 every request (license validation and rate limiting both use the cache).

**Tests:** Unaffected. `phpunit.xml` forces `CACHE_STORE=array`.

## 3. Purchaser Extension Distribution Route

Completes plan Task 18 Step 3 ("Serve packaged extensions") for end users.

**Route** (in `routes/api.php`, inside the existing `auth:sanctum` group):

```
GET /api/tools/{tool}/extension/{browser}
    where: browser ∈ chrome|firefox|edge
    throttle:10,10   (mirrors the existing download throttle)
```

**Controller:** new `extension(Request, Tool, string $browser)` method on the existing `DownloadController`. No new controller — follows the established `download`/`config` pattern and reuses `DownloadService`.

**Flow:**

1. Tool is not `type=extension` → **404**. No uploaded file → **404**.
2. `browser` not listed in the tool's `extension_meta.browsers` → **422** (validation error).
3. Call `DownloadService::download($user, $file)` — enforces active purchase (**403** `payment_required`) and returns the config payload (license token, api_url, tool_id).
4. Call `DownloadService::bundleZip($file, $config, $browser)` — injects `config.json` + browser-specific `manifest.json` + `extension.json`.
5. Stream the temp file as `{slug}-{browser}.zip` with `deleteFileAfterSend(true)`.

**On "signed":** the plan's "Serve signed/packaged extensions" means store-signed — Chrome Web Store / Firefox AMO sign add-ons at submission time. The server serves the packaged build; self-signing is not standard practice and is out of scope.

**Tests** (TDD, added to `tests/Feature/ExtensionTest.php`):

| Case | Expectation |
|---|---|
| Purchaser requests supported browser | 200, zip contains `config.json` (with token) + `manifest.json` (correct manifest version for that browser) |
| Non-purchaser (authenticated) | 403 `payment_required` |
| Unauthenticated | 401 |
| Browser not in `extension_meta.browsers` | 422 |
| Non-extension tool | 404 |
| 11th request within window | 429 |

## 4. Verification & Commits

- TDD for the extension route: write failing tests first, then implement, then green.
- Full suite: `php artisan test` — existing 148 tests plus the new extension-route tests must pass.
- Three commits, in order:
  1. `feat: gate admin dashboard sections by role` (frontend + `.gitignore`)
  2. `chore: add predis and wire redis-ready cache config`
  3. `feat: add purchaser extension distribution route`

## Out of Scope

- Flipping `CACHE_STORE=redis` (no server; Option A explicitly defers this).
- Storefront UI for per-browser download buttons (route only; can be a follow-up).
- Extension self-signing.
- Installing a Redis server (WSL/Memurai) — rejected in favor of Option A.

## Errata (post-implementation)

Approved during execution by reviewer prescriptions; commits beyond the original three:

- `8725a9b` — removed 63 previously-tracked Playwright snapshot files (§1 originally scoped only the untracked artifacts; gitignore alone cannot untrack files).
- `aef93df` — `config/database.php` Redis client default `'phpredis'` → `'predis'` (§2 said "no application code changes"; this config default was required so the one-line flip works on machines with older `.env` files).
- Extension tests: 8 new (spec table's 6 + reviewer-sanctioned no-file-404 + non-zip-404 zip guard added by quality review); suite landed at 156 tests, not the plan's 154.
- `c255233` — plan errata: red-phase failures arrive as catch-all 200s, not 404s.
