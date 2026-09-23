# Plan Completion (Redis-Ready Cache + Extension Distribution) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the outstanding role-gating frontend fixes, make the codebase Redis-ready without flipping the cache store, and add the purchaser-facing browser extension distribution route.

**Architecture:** Three independent commits on `main`: (1) frontend role-gating cleanup, (2) `predis/predis` + env wiring only (all cache access already goes through the default store, so `CACHE_STORE=redis` becomes a one-line future flip), (3) a new `extension()` method on the existing `DownloadController` reusing `DownloadService::download` (purchase check + config) and `DownloadService::bundleZip` (manifest/config injection), exposed as `GET /api/tools/{tool}/extension/{browser}`.

**Tech Stack:** Laravel 13, PHP 8.3, predis/predis 3.x, PHPUnit (RefreshDatabase, in-memory SQLite), Git.

**Spec:** `docs/superpowers/specs/2026-09-23-plan-completion-design.md`

---

### Task 1: Housekeeping — commit role-gating frontend + ignore Playwright artifacts

**Files:**
- Modify: `.gitignore`
- Delete: `.playwright-mcp/page-*.yml` (7 untracked artifacts)
- Commit: `resources/js/dashboard/App.jsx`, `resources/js/dashboard/components/Layout.jsx`, `resources/js/dashboard/components/ProtectedRoute.jsx`, `resources/js/dashboard/components/ToolTable.jsx`, `resources/js/dashboard/pages/Dashboard.jsx`, `resources/js/dashboard/pages/Tools.jsx` (all already modified, verified working via Playwright + 148 passing tests)

- [x] **Step 1: Delete Playwright snapshot artifacts**

```powershell
Remove-Item -Path ".playwright-mcp\page-*.yml" -Force
```

Expected: the 7 `page-*.yml` files are gone; `.playwright-mcp/` directory may remain empty.

- [x] **Step 2: Add `.playwright-mcp/` to `.gitignore`**

Append this line to `.gitignore` (after the existing entries):

```
/.playwright-mcp/
```

- [x] **Step 3: Verify the staged diff is only the intended files**

Run: `git status --short`

Expected output (order may vary):

```
 M .gitignore
 M resources/js/dashboard/App.jsx
 M resources/js/dashboard/components/Layout.jsx
 M resources/js/dashboard/components/ProtectedRoute.jsx
 M resources/js/dashboard/components/ToolTable.jsx
 M resources/js/dashboard/pages/Dashboard.jsx
 M resources/js/dashboard/pages/Tools.jsx
```

No `.playwright-mcp/` entries and no other files.

- [x] **Step 4: Commit**

```powershell
git add .gitignore resources/js/dashboard/App.jsx resources/js/dashboard/components/Layout.jsx resources/js/dashboard/components/ProtectedRoute.jsx resources/js/dashboard/components/ToolTable.jsx resources/js/dashboard/pages/Dashboard.jsx resources/js/dashboard/pages/Tools.jsx
git commit -m "feat: gate admin dashboard sections by role"
```

Expected: `[main <hash>] feat: gate admin dashboard sections by role` with 7 files changed.

---

### Task 2: Redis-ready cache — install predis and wire env

**Files:**
- Modify: `composer.json`, `composer.lock` (via `composer require`)
- Modify: `.env`
- Modify: `.env.example`

No application code changes: `LicenseController::validateRequest` uses `Cache::remember` on the default store and `LicenseCache` uses `Cache::get`/`Cache::increment` on the default store — flipping `CACHE_STORE` later moves license validation to Redis automatically. `config/database.php:148` already reads `env('REDIS_CLIENT', 'phpredis')` and `config/cache.php:81-85` already defines the `redis` store.

- [x] **Step 1: Install predis (pure-PHP Redis client — no phpredis extension needed)**

```powershell
composer require predis/predis
```

Expected: `predis/predis` added to `require` in `composer.json` (e.g. `^3.x`); lock file updated.

- [x] **Step 2: Update `.env`**

Change `REDIS_CLIENT=phpredis` to:

```
REDIS_CLIENT=predis
```

Then directly under `CACHE_STORE=database`, add this comment line:

```
CACHE_STORE=database
# CACHE_STORE=redis — flip this after starting a Redis server (license validation + rate limiting move to Redis; REDIS_* below)
```

(Keep `CACHE_STORE=database` active. Only the commented line is added.)

- [x] **Step 3: Mirror the same two changes in `.env.example`**

Change `REDIS_CLIENT=phpredis` to `REDIS_CLIENT=predis`, and add the same `# CACHE_STORE=redis — ...` comment under `CACHE_STORE=database`.

- [x] **Step 4: Run the full suite to confirm nothing broke**

Run: `php artisan test`

Expected: **all tests pass** (148 tests, 546 assertions — same as before; `phpunit.xml` forces `CACHE_STORE=array` so Redis is never touched in tests).

- [x] **Step 5: Commit**

```powershell
git add composer.json composer.lock .env.example
git commit -m "chore: add predis and wire redis-ready cache config"
```

Expected: `[main <hash>] chore: add predis and wire redis-ready cache config` with 3 files changed. (`.env` is gitignored and is not committed — its edit is local-only by design.)

---

### Task 3: Extension route — write failing tests (TDD red)

**Files:**
- Test: `tests/Feature/ExtensionTest.php` (append 6 test methods; existing helpers `makeExtZipFile()` and `buy()` are reused as-is)

- [x] **Step 1: Append the six failing tests to `ExtensionTest`**

Add these methods inside the `ExtensionTest` class (after `test_package_to_path_writes_zip_with_manifest`):

```php
    public function test_purchaser_can_download_extension_for_supported_browser(): void
    {
        $purchaser = User::factory()->create();
        $tool = Tool::factory()->create([
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => [
                'browsers' => ['chrome', 'firefox'],
                'manifest_version' => 3,
                'permissions' => ['storage'],
            ],
        ]);
        $this->makeExtZipFile($tool);
        $token = $this->buy($tool, $purchaser);

        $response = $this->actingAs($purchaser, 'sanctum')
            ->get("/api/tools/{$tool->id}/extension/firefox")
            ->assertOk()
            ->assertDownload("{$tool->slug}-firefox.zip");

        $tmp = tempnam(sys_get_temp_dir(), 'extdl');
        file_put_contents($tmp, $response->streamedContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);

        $config = json_decode($zip->getFromName('config.json'), true);
        $this->assertSame($token, $config['token']);
        $this->assertSame($tool->id, $config['tool_id']);

        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame(2, $manifest['manifest_version']);

        $zip->close();
        @unlink($tmp);
    }

    public function test_extension_download_requires_active_purchase(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->create([
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => ['browsers' => ['chrome'], 'manifest_version' => 3, 'permissions' => []],
        ]);
        $this->makeExtZipFile($tool);

        $this->actingAs($user, 'sanctum')
            ->get("/api/tools/{$tool->id}/extension/chrome")
            ->assertStatus(403)
            ->assertJson(['message' => 'payment_required']);
    }

    public function test_extension_download_rejects_unlisted_browser(): void
    {
        $purchaser = User::factory()->create();
        $tool = Tool::factory()->create([
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => ['browsers' => ['chrome'], 'manifest_version' => 3, 'permissions' => []],
        ]);
        $this->makeExtZipFile($tool);
        $this->buy($tool, $purchaser);

        $this->actingAs($purchaser, 'sanctum')
            ->get("/api/tools/{$tool->id}/extension/firefox")
            ->assertStatus(422);
    }

    public function test_extension_download_returns_404_for_non_extension_tool(): void
    {
        $purchaser = User::factory()->create();
        $tool = Tool::factory()->create([
            'type' => Tool::TYPE_DESKTOP,
            'extension_meta' => null,
        ]);
        $this->makeExtZipFile($tool);
        $this->buy($tool, $purchaser);

        $this->actingAs($purchaser, 'sanctum')
            ->get("/api/tools/{$tool->id}/extension/chrome")
            ->assertNotFound();
    }

    public function test_extension_download_requires_authentication(): void
    {
        $tool = Tool::factory()->create([
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => ['browsers' => ['chrome'], 'manifest_version' => 3, 'permissions' => []],
        ]);
        $this->makeExtZipFile($tool);

        $this->getJson("/api/tools/{$tool->id}/extension/chrome")
            ->assertUnauthorized();
    }

    public function test_extension_download_is_rate_limited(): void
    {
        $purchaser = User::factory()->create();
        $tool = Tool::factory()->create([
            'type' => Tool::TYPE_EXTENSION,
            'extension_meta' => ['browsers' => ['chrome'], 'manifest_version' => 3, 'permissions' => []],
        ]);
        $this->makeExtZipFile($tool);
        $this->buy($tool, $purchaser);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($purchaser, 'sanctum')
                ->get("/api/tools/{$tool->id}/extension/chrome")
                ->assertOk();
        }

        $this->actingAs($purchaser, 'sanctum')
            ->get("/api/tools/{$tool->id}/extension/chrome")
            ->assertStatus(429);
    }
```

- [x] **Step 2: Run the new tests to verify they fail**

Run: `php artisan test --filter=ExtensionTest`

Expected: **FAIL** — the route does not exist yet, so tests expecting 200/403/422/401/429 receive **200** (the SPA catch-all `Route::get('/{any}', ...)` in `routes/web.php` intercepts the non-existent API route and serves the app view) — not 404 as first drafted; all six new tests fail, including the non-extension 404 test (it also gets the catch-all 200). Failures are still due to the missing route, which satisfies the red-phase gate. No syntax/parse errors; the original 7 tests keep passing.

Do not proceed until the failures are due to the missing route, not a syntax error.

---

### Task 4: Extension route — implement (TDD green) and commit

**Files:**
- Modify: `routes/api.php` (inside the existing `auth:sanctum` group, after the `download/config` routes)
- Modify: `app/Http/Controllers/Api/DownloadController.php` (add one method; all needed imports — `JsonResponse`, `Request`, `Storage`, `BinaryFileResponse`, `Tool`, `DownloadService` — already present)

- [x] **Step 1: Add the route**

In `routes/api.php`, inside the `Route::middleware('auth:sanctum')->group(...)`, directly after the `download/config` route lines, add:

```php
        Route::get('/tools/{tool}/extension/{browser}', [DownloadController::class, 'extension'])
            ->where('browser', 'chrome|firefox|edge')
            ->middleware('throttle:10,10');
```

- [x] **Step 2: Add the controller method**

In `app/Http/Controllers/Api/DownloadController.php`, add this method after `config()`:

```php
    public function extension(Request $request, Tool $tool, string $browser): BinaryFileResponse|JsonResponse
    {
        if ($tool->type !== Tool::TYPE_EXTENSION) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $file = $tool->files()->latest('id')->first();

        if (! $file) {
            return response()->json(['message' => 'No file available for this tool.'], 404);
        }

        $browsers = $tool->extension_meta['browsers'] ?? [];

        if (! in_array($browser, $browsers, true)) {
            return response()->json([
                'message' => 'The selected browser is invalid.',
                'errors' => ['browser' => ['The selected browser is not supported for this tool.']],
            ], 422);
        }

        $result = $this->downloads->download($request->user(), $file);
        $bundle = $this->downloads->bundleZip($file, $result['config'], $browser);

        return response()->download($bundle, "{$tool->slug}-{$browser}.zip")
            ->deleteFileAfterSend(true);
    }
```

Flow matches the spec exactly: non-extension/no-file → 404, unlisted browser → 422, `download()` throws 403 `payment_required` when no active purchase, `bundleZip()` injects `config.json` + browser-specific `manifest.json` + `extension.json`, temp file deleted after send.

- [x] **Step 3: Run the extension tests to verify they pass**

Run: `php artisan test --filter=ExtensionTest`

Expected: **PASS** — all 13 tests (7 existing + 6 new).

- [x] **Step 4: Run the full suite**

Run: `php artisan test`

Expected: **156 tests, all passing** (148 existing + 8 new — 7 planned/sanctioned + zip-guard test from quality review).

- [x] **Step 5: Commit**

```powershell
git add routes/api.php app/Http/Controllers/Api/DownloadController.php tests/Feature/ExtensionTest.php
git commit -m "feat: add purchaser extension distribution route"
```

Expected: `[main <hash>] feat: add purchaser extension distribution route` with 3 files changed.

---

## Post-Plan Verification

- [x] `git log --oneline -5` shows the three new commits (plus the earlier spec commit) on `main`.
- [x] `git status --short` is clean.
- [x] Full suite green: `php artisan test` → 154 passing.
