# Translation Manager Overview

Translation Manager is a Capell admin package for managing Laravel language files from Filament, with safe package override writes and optional reviewed AI drafting.

The package is file-first. App language files are editable in place. Package and vendor files are treated as read-only source material unless explicitly configured otherwise; edits are written to Laravel override paths so package upgrades remain safe.

Phase one does not create Capell language records, database tables, jobs, or frontend output.

## Installation Audit

- Composer package: `capell-app/translation-manager`
- Hard dependencies: `capell-app/admin`, `capell-app/core`
- Optional dependencies: `capell-app/ai-orchestrator` for reviewed AI translation drafts; `capell-app/seo-suite` for pairing translation coverage with multilingual SEO and AI-discovery workflows
- Database impact: no migrations or settings tables owned by this package
- Public frontend impact: none

The package registers `TranslationManagerServiceProvider` through Composer and declares `AdminServiceProvider` in `capell.json`. In the isolated harness, the extension installed successfully and exposed the `admin/translation-manager` Filament route.

## Admin Surfaces

- Translation Manager page: Filament page at `admin/translation-manager`
- Header action modal: Create locale
- Header action modal: Duplicate locale
- Header action: Export CSV
- Header action: Export XLIFF
- Header action: Export PO/gettext
- Header action modal: Import translations from CSV, XLIFF, or PO contents
- Header action: Publish readiness notification for the selected target locale
- Header action: Scan missing keys referenced in code
- Header action: Save translations
- Optional header action: Translate selected, visible only when an AI translator binding is available
- Per-locale publish-readiness matrix with file, entry, missing, stale, changed, extra, and ready counts
- Translation comparison grid with source selector, locale selectors, file selector, status filter, entry checkboxes, source text, and target textareas
- Action APIs for stale translation detection, CSV and XLIFF import/export, and per-locale publish readiness checks

The status filter includes a combined "Needs attention" view for missing, stale, and changed entries. Selectors and entry checkboxes use Filament input components, and target textareas are wrapped in Filament input wrappers for consistent theming. Import/export supports CSV, XLIFF, and PO/gettext for the selected file.

## Data Integrity

Package and vendor reads merge source files with Laravel override files before comparison. A partial override therefore no longer hides un-overridden source keys in the editor.

Save and import operations validate non-empty target values against the configured source locale. Target strings must preserve source placeholders such as `:count`, and pipe-delimited plural strings must keep the same number of plural forms. Blank target values remain allowed so untranslated keys can stay intentionally missing.

When a target file is saved or imported, the store records source hashes per key in a `*.capell-meta.json` sidecar file. Later comparisons use those hashes to mark only keys whose own source string changed as stale. Files without metadata fall back to the legacy file-mtime heuristic.

If `app.fallback_locale` points at another valid locale, target keys with no value but a fallback value are marked `fallback` instead of `missing`. The readiness matrix reports those separately from true gaps.

## Missing Key Scans

`ScanMissingTranslationKeysAction` scans configured paths for `__()`, `trans()`, `trans_choice()`, and `@lang()` calls, then compares those references with the selected source locale. The page exposes this as a header action and displays missing key, file, and line results when gaps are found.

Configure paths with `capell-translation-manager.scan_paths`. The app source ignores namespaced package keys; package sources match their translation namespace prefix.

## AI Review

Translation memory runs before AI drafting. Exact source-string matches from existing translated entries are suggested immediately for selected keys. AI drafting fills remaining selected keys when an AI translator is available.

AI and memory suggestions are added to pending page state. Editors review each suggestion inline, then accept it into the target textarea or reject it. Suggestions are not written to disk until accepted and saved.

## Glossary

Configured glossary terms are enforced during save and import. If a source string contains a glossary source term, the target value must contain the configured target term for that locale. Configure terms with `capell-translation-manager.glossary.{locale}`.

## Screenshot Coverage

See [screenshots.json](screenshots.json) for the screenshot contract. The shipped PNG documentation assets cover the no-results state, a populated comparison grid, the create locale modal, the duplicate locale modal, and the AI translate-selected review state.

The optional Translate selected action is intentionally absent unless a `TranslationAITranslator` implementation is available, normally through `capell-app/ai-orchestrator`.

## Disposable Harness Notes

- Install only the core Capell stack and `capell-app/translation-manager` for screenshots.
- Remove unrelated extensions from the harness database before capture; the verified pass left only `capell-app/translation-manager` enabled.
- Leave `capell-translation-manager.package_paths` empty unless the harness needs explicit non-Composer translation sources. Composer-installed packages are discovered automatically.
- Seed a small application translation fixture, such as `lang/en/package.php` and partial `lang/fr/package.php`, so the grid shows missing and changed rows without depending on another package.

## Verification

- `vendor/bin/pest packages/translation-manager/tests --configuration=phpunit.xml`
- `php artisan route:list | rg 'translation|Translation|translations'` in the disposable harness
- Browser capture at `/admin/translation-manager` with `admin@example.test` / `password`
