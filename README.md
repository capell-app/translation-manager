# Translation Manager

File-based Laravel translation management for Capell and Filament admin panels with side-by-side locale editing, missing and stale key checks, placeholder/plural validation, reviewed AI drafting when configured, and safe package override writes.

## At A Glance

- Package: `capell-app/translation-manager`
- Namespace: `Capell\TranslationManager\`
- Surfaces: Filament admin
- Service providers: `packages/translation-manager/src/Providers/AdminServiceProvider.php`, `packages/translation-manager/src/Providers/TranslationManagerServiceProvider.php`
- Capell dependencies: `capell-app/admin`, `capell-app/core`
- Third-party dependencies: `lorisleiva/laravel-actions`, `spatie/laravel-data`, `spatie/laravel-package-tools`

## Why It Helps Your Capell Workflow

- Provides a file-based Filament editor for Laravel language files with safe package override writes.
- Helps owners and admins adjust labels and copy without editing vendor package files directly.
- Gives developers a controlled translation workflow that respects package boundaries and override storage.
- Keeps package override reads aligned with Laravel runtime behaviour by merging package source files with partial override files.
- Prevents saved or imported translations from dropping source placeholders or plural forms once a target value is provided.
- Tracks source hashes per saved key so unrelated source-file edits do not mark every target key stale.
- Distinguishes truly missing keys from keys covered by Laravel's configured fallback locale.
- Scans configured application paths for `__()`, `trans()`, `trans_choice()`, and `@lang()` references that do not exist in the selected source locale.
- Keeps AI suggestions in a review state until an editor accepts or rejects each suggestion when an AI translator is configured.
- Reuses exact source-string matches from existing translations as translation-memory suggestions before calling AI.
- Pairs with SEO Suite when multilingual teams need translation coverage alongside search, metadata, and AI-discovery workflows.
- Enforces configured glossary terms during save/import.

## Best Used With

- [AI Orchestrator](../ai-orchestrator/README.md) for reviewed translation drafts
- [Diagnostics](../diagnostics/README.md)
- [SEO Suite](../seo-suite/README.md) for multilingual SEO and AI-discovery coverage
- [Welcome Tour](../welcome-tour/README.md)

## What It Adds

- File-based Laravel translation management for Capell and Filament admin panels with missing and stale key checks, safe package override writes, and optional reviewed AI drafting.
- Locale creation and duplication from the package admin page.
- CSV, XLIFF, and PO/gettext import/export actions for the selected translation file.
- A per-locale publish-readiness matrix showing missing, stale, changed, and extra counts.
- A combined "Needs attention" filter for missing, stale, and changed entries.
- Per-key stale detection for translations saved through the package, with file-mtime fallback for legacy files without metadata.
- Fallback-locale awareness so readiness reports can show keys covered by `app.fallback_locale`.
- A missing-key scan action for code references that are absent from language files.
- Translation-memory suggestions and glossary validation for consistent wording.
- Filament-native selector, checkbox, and input-wrapper controls in the translation grid.
- Optional AI translation only when a host application binds a translator implementation, typically through AI Orchestrator.

## Code Map

| Area      | Path                                         | Purpose                                                             |
| --------- | -------------------------------------------- | ------------------------------------------------------------------- |
| Actions   | `packages/translation-manager/src/Actions`   | Domain operations. Test these directly where possible.              |
| Data      | `packages/translation-manager/src/Data`      | Structured payloads, form state, view models, and integration data. |
| Filament  | `packages/translation-manager/src/Filament`  | Admin resources, pages, widgets, and settings UI.                   |
| Providers | `packages/translation-manager/src/Providers` | Registration, extension hooks, routes, migrations, and resources.   |
| Resources | `packages/translation-manager/resources`     | Views, translations, assets, and package resources.                 |
| Config    | `packages/translation-manager/config`        | Package configuration and publishable config.                       |
| Tests     | `packages/translation-manager/tests`         | Package-level Pest coverage.                                        |

## Admin Surface

- Pages: `TranslationManagerPage` at `/admin/translation-manager`.
- Header actions: Create locale, Duplicate locale, Export CSV, Export XLIFF, Export PO, Import translations, Publish readiness, Scan missing keys, Save translations.
- Optional header action: Translate selected, only when an AI translator binding is available.

## Data And Persistence

- Config: `packages/translation-manager/config/capell-translation-manager.php`.
- Data objects live in `src/Data/`; use them for payloads, form state, and view models.
- Package and vendor override reads merge the source file with the override file. Writes still target Laravel override paths unless `package_source_writes` is explicitly enabled.
- Save and import operations validate non-empty target values against the configured source locale. Targets must preserve source `:placeholders` and the same plural pipe count.
- Save and import operations record per-key source hashes beside the target file in `*.capell-meta.json` metadata. Existing target files without metadata continue to use the older file-mtime stale heuristic until they are saved again.
- Code scanning paths are configured through `capell-translation-manager.scan_paths`.
- Glossary terms are configured through `capell-translation-manager.glossary.{locale}`.
- AI suggestions are stored in page state and must be accepted before they become target values.
- XLIFF and PO export actions build one selected language file at a time in memory; keep very large translation sets split by file unless a future scale task introduces streamed export writers.

## Extension Points

- Contracts: `TranslationAITranslator`, `TranslationFileStore`, `TranslationSourceResolver`.
- Register Capell extension points, routes, migrations, settings, render hooks, and resources from service providers.

## Install And Setup

- Install with `composer require capell-app/translation-manager` in the host Capell application.
- In this repository, verify package changes with `vendor/bin/pest`; do not use `php artisan`.
- For screenshots in a local package harness, set `capell-translation-manager.package_paths` to `[]` unless the pass is intentionally documenting package translation sources. This keeps uninstalled sibling package repositories out of the source selector.

## Docs

- [docs index](docs/README.md)
- [overview.md](docs/overview.md)
- [screenshots.json](docs/screenshots.json)
- [sources-stores-and-ai.md](docs/sources-stores-and-ai.md)

## Testing

Run package tests from the repository root:

```bash
vendor/bin/pest packages/translation-manager/tests --configuration=phpunit.xml
```

## Maintenance Notes

- Put behaviour changes in `src/Actions/`; UI classes, commands, and controllers should call actions instead of owning domain logic.
- Use package `Data` classes at boundaries instead of passing anonymous arrays between layers.
