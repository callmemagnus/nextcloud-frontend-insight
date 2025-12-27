# FrontendInsight – Engineering Guide (AGENTS)

This document captures practical conventions, commands, and patterns for working on this repository. It is intended for both humans and automation agents.

## Project overview
- Stack: Nextcloud app (PHP 8.0+), Composer (dev), JS/TS with esbuild, Svelte 5 (runes API), Axios via `@nextcloud/axios`, routing via `@nextcloud/router`, initial state via `@nextcloud/initial-state`.
- Bundles:
  - `js/client.js`: browser client that reports frontend errors to the app backend (consumes initial state)
  - Event browser (Svelte) built via Vite for the admin UI

## Prerequisites
- PHP: 8.0.x supported by composer platform (tooling may run on newer PHP, but project compatibility targets 8.0)
- Node.js: >= 18 (tested with Node 20)
- Composer: use the checked-in `composer.phar` for reproducible operation

## Common commands
- Build frontend
  - Event browser: `npm run build:event-browser`
  - Client: `npm run build:client`
  - All bundles: `npm run build`
- PHP checks
  - Lint: `php composer.phar run lint`
  - Static analysis (Psalm): `php composer.phar run psalm -n`
  - Coding style (check): `php composer.phar run cs:check`
  - Coding style (fix): `php composer.phar run cs:fix`
- Dependency update
  - JS: `npm outdated` / `npm update`
  - PHP: `php composer.phar outdated` / `php composer.phar update --prefer-stable`

## Dependency policy
- JavaScript
  - Use exact or caret constraints for devDependencies as appropriate.
  - Svelte 5 is in use; prefer the runes API (e.g., `$state`). Ensure code remains compatible with that version.
  - esbuild is used both directly and via `esbuild-svelte` plugin.
- PHP
  - `composer.json` pins platform to PHP 8.0.2 for Nextcloud app compatibility.
  - Keep `nextcloud/ocp` at the branch matching the targeted Nextcloud stable (currently `dev-stable29`).
  - Prefer stable updates with `--prefer-stable`; avoid forcing major upgrades of transitive deps that conflict with OCP.

## PHP code guidelines
- Namespaces: `OCA\FrontendInsight\...`
- Controllers extend `OCP\AppFramework\Controller\Controller` and may use attributes like `#[CORS]` and `#[ApiRoute]`.
- DB layer
  - Entities in `lib/Db/*` and mappers extend `OCP\AppFramework\Db\QBMapper`.
  - Always add generics annotation for mappers, e.g., `@extends QBMapper<Event>` to satisfy Psalm.
  - Prefer `selectDistinct('col')` over lower-level helpers for portability across OCP versions.
- Migrations
  - Use `OCP\Migration\SimpleMigrationStep` with `ISchemaWrapper`.
  - Ensure `hasTable()` and `createTable()` use the same table name.
  - Avoid referencing Doctrine classes directly in docblocks; rely on OCP abstractions. If necessary, suppress with a scoped Psalm suppression.
- Logging
  - Use PSR-3 `Psr\Log\LoggerInterface` injected via DI.

## JavaScript/Svelte guidelines
- Svelte 5 runes: state variables use `$state(...)` and lifecycle via `onMount`.
- Custom element: `src/backoffice/App.svelte` is compiled with `customElement: true` and exported as `<fei-backoffice>`.
- HTTP
  - Use `@nextcloud/router` `generateUrl()` for app routes, including templated version segments.
  - Use `@nextcloud/axios` for requests; it’s pre-configured for Nextcloud auth/headers.
- Error reporting
  - `src/client/index.ts` listens for `unhandledrejection` and `error` and posts to `/apps/frontend_insight/report/error`.
  - Admin settings control what is collected via initial state provided in `Application::boot()` and consumed via `@nextcloud/initial-state`.
- Event viewer UI
  - Shows the time the first event was received (hidden if not available)
  - Shows per-type counts (hidden if empty)
  - Pagination uses `page` and `limit` query params

## API and routing

See API.md for full API reference.

- Routes are defined in `appinfo/routes.php`:
  - GET `/client.js` – serves the client bundle
  - POST `/report/error` – receives error reports
  - GET `/api/1.0/events` – pagination params: `page`, `limit`, optional `url` filter
  - GET `/api/1.0/stats` – returns `{ firstTimestamp: number | null, byType: Record<string, number> }`
  - GET/POST `/settings` – admin read/save of settings (admin-only via controller check)
    - GET returns JSON with: `collect_errors`, `collect_unhandled_rejections`, `retention_hours`.
    - POST accepts form fields and responds based on `Accept` header:
      - `application/json` → `{"success": true}` (for progressive enhancement)
      - otherwise → HTTP redirect back to the admin settings page
  - OPTIONS CORS preflight for `/api/1.0/{path}`
- Use attributes in controllers:
  - `#[ApiRoute(verb: 'GET', url: '/api/{version}/events')]`
  - `#[CORS]` for cross-origin where required

## Security considerations
- CSP: `Application::boot()` injects a script via `Util::addHeader` and configures CSP using the CSP nonce.
- Initial state is provided with `Util::addInitialState`; do not expose secrets.
- When adding external resources, update CSP to whitelist domains as needed.
- Validate/escape user-provided data surfaced in templates or logs.

## Testing and quality
- PHP lint and Psalm must pass with zero errors.
- Run code style checks and prefer `cs:fix` to apply standard formatting.
- There are currently no automated JS tests; consider adding lightweight checks if complexity grows.
- After changing settings behavior that affects the client, rebuild the client bundle: `npm run build:client`.

## Release checklist
- [ ] Update dependencies (npm/composer) and rebuild bundles
- [ ] Run `lint`, `psalm`, `cs:check`
- [ ] Verify routes and APIs still function (manual or integration environment)
- [ ] Update CHANGELOG/Release notes (if applicable)

## Troubleshooting
- Psalm errors in migrations often stem from docblock types referencing Doctrine classes; prefer OCP interfaces or add a narrow `@psalm-suppress`.
- If DB query methods are missing (e.g., `getColumnName`), prefer `IQueryBuilder` methods (`selectDistinct`, `createNamedParameter`, etc.).
- If PHP-CS-Fixer warns about PHP version mismatch, remember project target is PHP 8.0; avoid introducing 8.1+ syntax.

## Automation agent notes
- Work within repository root; do not write outside.
- Prefer Composer/Node commands over raw shell operations for dependency management.
- After making code changes, always:
  1) Run `npm run check` (Svelte type and diagnostics)
  2) Run `npm run build` (build event browser and client bundles)
  3) Run PHP lint, Psalm, and code style
- When upgrading dependencies, avoid major version jumps that conflict with Nextcloud OCP unless explicitly requested.

## Useful snippets
- Distinct URLs with QueryBuilder (PHP):
  ```php
  $qb = $this->db->getQueryBuilder();
  $urls = $qb->selectDistinct('url')
    ->from($this->getTableName())
    ->setMaxResults(10)
    ->executeQuery()
    ->fetchAll();
  ```
- Svelte data load pattern (TS):
  ```svelte
  <script lang="ts">
    import { onMount } from 'svelte';
    import { generateUrl } from '@nextcloud/router';
    import axios from '@nextcloud/axios';

    let rows = $state([]);
    let loading = $state(false);

    async function load() {
      loading = true;
      const url = generateUrl('/apps/frontend_insight/api/1.0/events');
      const { data } = await axios.get(url);
      rows = data.values;
      loading = false;
    }

    onMount(load);
  </script>
  ```

## Housekeeping
- Keep `psalm.xml` `phpVersion` aligned with composer platform if the language level changes.
- Maintain consistent table names between migrations, mappers, and queries (e.g., `fe_errors`).
- Ensure controller attributes reflect routes in `appinfo/routes.php`.

