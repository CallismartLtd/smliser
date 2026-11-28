# AGENTS.md

Guidance for AI coding agents (and humans) working on this repository.

## What this is

Smart License Server (`callismart/smart-license-server`): a PHP 8.4+ app that hosts
WordPress plugins, themes and other software, and issues, activates and
monetizes licenses for them. It runs **standalone** or as a **WordPress plugin**.
Code is PSR-4 under `SmartLicenseServer\` → `src/`.

| Area | Where |
|---|---|
| Admin dashboard | `src/Admin/` (pages in `ContentHandlers/`), templates in `templates/admin/` |
| Client dashboard | `src/ClientDashboard/` (pages in `ContentHandlers/`, JSON actions in `ActionHandlers/`, menu in `ClientDashboardRegistry.php`), templates in `themes/default/templates/pages/client/` |
| Standalone routes | `src/Environments/Application/Web/HttpDispatcher.php` |
| WordPress glue | `src/Environments/WordPress/` |
| Hosted apps | `src/HostedApps/` (`HostedAppsRegistry`, `HostingController`, `OwnerAppScope`) |
| Licenses, pricing | `src/Monetization/` (`License`, `LicensingService`, `Controller`) |
| Public REST API | `src/RESTAPI/` (`Versions/V1.php`, `Handlers/`) |
| Security | `src/Security/` (`SecurityAwareTrait`, `Permission/DefaultRoles.php`, sessions) |
| Settings | `src/SettingsAPI/` (`Settings` site-wide, `UserSettings` per user) |
| Translations | `src/I18n/`, catalogs in `languages/`, guide in `docs/i18n.md` |
| DB migrations | `src/Schema/Migrations/`, registered in `src/Schema/MigrationRegistry.php` |

## Commands

```bash
composer install
vendor/bin/phpunit              # PHP tests (no database needed)
node tests/js/i18n.test.js      # JS translation helpers
composer cs:check               # code style (php-cs-fixer); cs:fix to apply
php -l path/to/File.php         # lint every PHP file you change
```

Tests build objects in memory; nothing runs against a real database or browser,
so say so when you report on database- or UI-heavy work.

## Code style

- `declare( strict_types = 1 );`, typed properties, parameter and return types.
- Tabs, LF, short arrays, braces on the same line, Yoda conditions (`null === $x`).
- One space inside parentheses (`foo( $a )`) and before return types (`function foo() : string`).
- Follow the existing pattern of the nearest similar class (page, action handler,
  template) instead of inventing a new structure.

## Rules that must not be broken

**Output and strings**
- Every user-facing string goes through the i18n helpers: `smliser__()`,
  `smliser_esc_html_e()`, `smliser_x()`, `smliser_n()`, ... (domain `smliser`); in JS
  `window.smliserI18n.__()`. Literal strings only, placeholders not concatenation,
  `translators:` comments for placeholders. Stored data stays in English.
- Escape all output with `escHtml()`, `escAttr()`, `escUrl()`.

**Security**
- Every state-changing request uses the existing CSRF middleware; client JSON
  actions go in the client `json` route group in `HttpDispatcher`.
- Check permissions and ownership server-side; never trust IDs, types or owners
  from the request. Unknown or foreign records answer **404**, missing capability **403**.
- For a record that belongs to an app (license, monetization, app files) use
  `check_record_app_ownership()` from `SecurityAwareTrait`: it **fails closed**
  (no resolvable app → system admin only). Never write `if ( $app ) check_app_ownership( $app )`.
- Client-dashboard app access goes through `HostedApps\OwnerAppScope`; app-owner
  license access through `ClientDashboard\AppOwnerScope`.

**Licenses and sites (product decisions)**
- In the dashboards, only the **owner of the license's app** (roles holding
  `monetization.license.uninstall_domain`: `resource_owner`, `organization_admin`,
  `resource_admin`, `license_manager`) or a system admin may free a site. A license
  holder sees sites read-only and may only deactivate the whole license; they can't
  reactivate it.
- The **public REST API** is different: anyone with the license key and service ID
  may uninstall (free) a licensed site. Don't add a site-secret requirement to it.
- Organization `viewer` and `analyst` roles get read-only access to their
  organization's apps.

**WordPress**
- Under WordPress, WordPress owns the locale, profile, password, sessions and site
  icon: hide those features there (`I18n::translator() instanceof WPTranslator` or
  `is_wp()`). Client-dashboard JSON routes are currently standalone-only.

## Translations workflow

After adding or changing strings:

1. Regenerate `languages/smliser.pot` with `SmartLicenseServer\I18n\Tools\StringExtractor`
   (sources: `src`, `templates`, `themes/default`, `assets/js`; base dir = repo root;
   keep the existing headers except `POT-Creation-Date`). The extractor warns about
   non-literal calls: fix them, don't ignore them.
2. `PoMerger::merge()` it into every `languages/smliser-<locale>.po` (not `smliser-js-*`).
3. Translate every new string in all 8 locales: ar, de_DE, es_ES, fr_FR, it_IT, pt_BR,
   ru_RU, zh_CN, reusing each catalog's existing wording. Catalogs must stay 100%.
4. `CatalogCompiler::compile()` each `.po` (writes `.l10n.php`, `.mo` and `smliser-js-*`).

The `smliser i18n make-pot|update|compile|status` CLI commands do the same when the
app's console is available. Put one-off helper scripts outside the repo.

## Database changes

Default roles and capabilities are stored in the database. Changing
`DefaultRoles.php` only affects fresh installs. For existing sites add a reversible
migration under `src/Schema/Migrations/`, register it, and bump `SMLISER_DB_VER` in
`src/constants.php`.

## Git

- Work on a branch, not `main`. Keep unrelated changes out of your commits.
- `vendor/` and `GEMINI.md` are gitignored; never commit them.
- Several branches regenerating `languages/` always conflict: when merging, take
  either side for `languages/`, then regenerate once and refill translations from
  all branches.
