# CLAUDE.md — PasswordVault

Self-hosted password manager (Laravel 13 + Inertia/Vue + Fortify, SQLite). Deployed to
the mini-PC on HTTPS ports 449 and 88 (nginx terminates TLS itself); deploy with
`/home/gemini/websites/deploy.sh PasswordVault`.

- **Never regenerate the production `APP_KEY`** — every secret is stored with Laravel's
  `encrypted` cast, so a new key makes the whole vault unreadable.
- **Never add a plain-HTTP listener** — `SESSION_SECURE_COOKIE=true`.
- An item is name + folder + favorite + ordered **fields**; username/password/website are
  just fields. `App\Support\FieldRoles` decides which field autofill, the list subtitle
  and export treat as the username / password / TOTP / URLs.

## Gates

`php artisan test` (Pest) · `vendor/bin/pint --dirty` · `vendor/bin/phpstan analyse` ·
`npm run lint:check` · `npm run types:check` · `npm run format:check` · **Dusk (below)**.
Pint, ESLint, Prettier and Larastan report some older findings in files outside recent
work; don't let them block, and don't mass-reformat unrelated files.

## Browser tests (Dusk)

**Verify anything browser-side with Laravel Dusk (`tests/Browser/*`), not screenshots.**
If a change needs coverage that doesn't exist yet, add a Dusk test.

Run the whole suite with:

```bash
scripts/dusk.sh                    # builds assets, serves 127.0.0.1:8449, runs Dusk
SKIP_BUILD=1 scripts/dusk.sh       # reuse public/build
scripts/dusk.sh --filter=history   # extra args go to `php artisan dusk`
```

The script is the estate recipe (HUB `STANDARDS.md` §3): it swaps `.env.dusk.local` in
as `.env`, fresh-migrates `database/testing/dusk.sqlite`, serves on port 8449, runs
`PAO_DISABLE=1 php artisan dusk`, then restores `.env` and stops the server even when
tests fail. Server request log: `storage/logs/dusk-serve.log`.

First-time setup: `cp .env.dusk.example .env.dusk.local`, then set `APP_KEY` from
`php artisan key:generate --show` (a throwaway key, never the production one), and
`php artisan dusk:chrome-driver --detect` if Chrome has updated.

Conventions:
- `@name` selectors resolve to **`data-test="name"`** (set in `tests/DuskTestCase.php`),
  the attribute this app already used — add `data-test` hooks, not `dusk=`.
- Use `$this->settle($browser)` / `openNewItem()` / `openItem()` before typing: the item
  sheet slides in over ~500ms and keystrokes during the animation are lost.
- Phone layouts: `emulateMobileViewport()` + `assertNoHorizontalOverflow()` (Windows
  ignores `resize(375, …)`).
- Sign-in tests start from a fresh browser (`closeAll()` in `beforeEach`): after a real
  password sign-in, Chrome's "save password?" prompt holds keyboard focus.
- `DatabaseMigrations` rolls every migration back after each test, so every migration's
  `down()` must work on SQLite (drop an index before dropping its column).
