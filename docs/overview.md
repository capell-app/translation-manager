# Translation Manager Overview

Translation Manager is a Capell admin package for managing Laravel language files from Filament.

The package is file-first. App language files are editable in place. Package and vendor files are treated as read-only source material unless explicitly configured otherwise; edits are written to Laravel override paths so package upgrades remain safe.

Phase one does not create Capell language records, database tables, jobs, or frontend output.

## Installation Audit

- Composer package: `capell-app/translation-manager`
- Hard dependencies: `capell-app/admin`, `capell-app/core`
- Optional dependencies: `capell-app/ai-orchestrator` for AI translation drafting
- Database impact: no migrations or settings tables owned by this package
- Public frontend impact: none

The package registers `TranslationManagerServiceProvider` through Composer and declares `AdminServiceProvider` in `capell.json`. In the isolated harness, the extension installed successfully and exposed the `admin/translation-manager` Filament route.

## Admin Surfaces

- Translation Manager page: Filament page at `admin/translation-manager`
- Header action modal: Create locale
- Header action modal: Duplicate locale
- Header action: Save translations
- Optional header action: Translate selected, visible only when an AI translator binding is available
- Translation comparison grid with source selector, locale selectors, file selector, status filter, entry checkboxes, source text, and target textareas

## Screenshot Coverage

See [screenshots.json](screenshots.json) for the screenshot contract. The verified capture covers the no-results state, a populated comparison grid, the create locale modal, and the duplicate locale modal.

The optional Translate selected action is intentionally absent unless a `TranslationAITranslator` implementation is available, normally through `capell-app/ai-orchestrator`.

## Disposable Harness Notes

- Install only the core Capell stack and `capell-app/translation-manager` for screenshots.
- Remove unrelated extensions from the harness database before capture; the verified pass left only `capell-app/translation-manager` enabled.
- Set `capell-translation-manager.package_paths` to `[]` in the harness config when the app sits beside `capell-packages-4`. Otherwise the source selector lists every local package repository, including packages that are not installed in the demo app.
- Seed a small application translation fixture, such as `lang/en/package.php` and partial `lang/fr/package.php`, so the grid shows missing and changed rows without depending on another package.

## Verification

- `vendor/bin/pest packages/translation-manager/tests --configuration=phpunit.xml`
- `php artisan route:list | rg 'translation|Translation|translations'` in the disposable harness
- Browser capture at `/admin/translation-manager` with `admin@example.test` / `password`
