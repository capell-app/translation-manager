# Translation Manager

<!-- prettier-ignore-start -->

## What This Plugin Adds

Translation Manager is an **Available**, **Schema-owning** Capell package in the **Capell Publishing** product group. It ships as `capell-app/translation-manager` and extends these surfaces: admin.

Translation Manager adds an admin workspace for comparing Laravel locales, finding missing or stale entries, and creating, duplicating, importing, exporting, or saving translation files. It works through file storage rather than adding translation tables.

Administrators can compare locale coverage, edit entries, and manage locale files from one Filament page.

Evidence: [`src/Filament/Pages/TranslationManagerPage.php`](src/Filament/Pages/TranslationManagerPage.php), [`src/Actions/LoadTranslationComparisonAction.php`](src/Actions/LoadTranslationComparisonAction.php), [`src/Actions/SaveTranslationEntriesAction.php`](src/Actions/SaveTranslationEntriesAction.php), [`src/Support/FileTranslationFileStore.php`](src/Support/FileTranslationFileStore.php), [`src/Actions/CreateLocaleFilesAction.php`](src/Actions/CreateLocaleFilesAction.php), [`src/Actions/DuplicateLocaleAction.php`](src/Actions/DuplicateLocaleAction.php), [`tests/Feature/Filament/TranslationManagerPageTest.php`](tests/Feature/Filament/TranslationManagerPageTest.php).

Status details:

- Status: Available
- Tier: free
- Bundle: publishing
- Composer package: `capell-app/translation-manager`
- Namespace: `Capell\TranslationManager`
- Theme key: not applicable

## Why It Matters

**For developers:** A TranslationFileStore contract separates file access from focused comparison and write Actions, so storage behavior remains testable.

**For teams:** Content and localization teams can close locale gaps while keeping changes in normal Laravel language files and package overrides.

Evidence: [`src/Contracts/TranslationFileStore.php`](src/Contracts/TranslationFileStore.php), [`src/Support/FileTranslationFileStore.php`](src/Support/FileTranslationFileStore.php), [`src/Actions/LoadTranslationComparisonAction.php`](src/Actions/LoadTranslationComparisonAction.php), [`tests/Feature/TranslationManagerActionsTest.php`](tests/Feature/TranslationManagerActionsTest.php), [`src/Filament/Pages/TranslationManagerPage.php`](src/Filament/Pages/TranslationManagerPage.php), [`tests/Feature/Filament/TranslationManagerPageTest.php`](tests/Feature/Filament/TranslationManagerPageTest.php).

## Screens And Workflow

Screenshot contract: `docs/screenshots.json`.

![Illustrative translation manager page with source, locale, file, and filter selectors in the no-results state preview](docs/screenshots/translation-manager-page-empty-state.png)

![Illustrative translation comparison grid with source strings, editable target strings, statuses, and entry selection preview](docs/screenshots/translation-manager-comparison-grid.png)

- Illustrative translation manager page with source, locale, file, and filter selectors in the no-results state preview (frontend, required evidence).
- Illustrative translation comparison grid with source strings, editable target strings, statuses, and entry selection preview (frontend, required evidence).
- Illustrative create locale modal preview (frontend, required evidence).
- Illustrative duplicate locale modal preview (frontend, required evidence).
- Illustrative translate selected action visible with ai translator support preview (frontend, supplementary documentation fixture).
- Illustrative dirty and missing translation review state preview (frontend, supplementary documentation fixture).
- Illustrative translation import and export actions preview (frontend, supplementary documentation fixture).
- Illustrative ai translation suggestion review preview (frontend, supplementary documentation fixture).
- Illustrative locale publish readiness summary preview (frontend, supplementary documentation fixture).

## Technical Shape

### Service providers

- `Capell\TranslationManager\Providers\TranslationManagerServiceProvider`
- `Capell\TranslationManager\Providers\AdminServiceProvider`

### Config files

- `packages/translation-manager/config/capell-translation-manager.php`

### Migrations

- `packages/translation-manager/database/migrations/2026_07_19_120000_create_translation_scan_runs_table.php`

### Models

- `TranslationScanRun`

### Filament classes

- `TranslationManagerPage`

### Extension contracts

- `TranslationAITranslator`
- `TranslationFileStore`
- `TranslationSourceResolver`

### Actions

- `BuildLocalePublishReadinessAction`
- `BuildTranslationMemorySuggestionsAction`
- `BuildTranslationReadinessMatrixAction`
- `CreateLocaleFilesAction`
- `DuplicateLocaleAction`
- `ExportTranslationEntriesToCsvAction`
- `ExportTranslationEntriesToPoAction`
- `ExportTranslationEntriesToXliffAction`
- `FilterTranslationEntriesAction`
- `FindNextTranslationWorkAction`
- `ImportTranslationEntriesFromCsvAction`
- `ImportTranslationEntriesFromPoAction`
- `ImportTranslationEntriesFromXliffAction`
- `ListInstalledLocalesAction`
- `ListTranslationFilesAction`
- `ListTranslationSourcesAction`
- `LoadTranslationComparisonAction`
- `QueueTranslationScanAction`
- `SaveTranslationEntriesAction`
- `ScanMissingTranslationKeysAction`
- `TranslateSelectedEntriesAction`

### Data objects

- `AITranslationSuggestionData`
- `LocalePublishReadinessData`
- `LocaleSummaryData`
- `MissingTranslationKeyData`
- `TranslationCsvImportResultData`
- `TranslationEntryData`
- `TranslationFileData`
- `TranslationQueueItemData`
- `TranslationSourceData`
- `TranslationWriteData`

### Jobs

- `RunTranslationScanJob`

### Manifest action API

- `buildLocalePublishReadiness: Capell\TranslationManager\Actions\BuildLocalePublishReadinessAction`
- `buildTranslationMemorySuggestions: Capell\TranslationManager\Actions\BuildTranslationMemorySuggestionsAction`
- `createLocaleFiles: Capell\TranslationManager\Actions\CreateLocaleFilesAction`
- `duplicateLocale: Capell\TranslationManager\Actions\DuplicateLocaleAction`
- `exportTranslationEntriesToCsv: Capell\TranslationManager\Actions\ExportTranslationEntriesToCsvAction`
- `exportTranslationEntriesToPo: Capell\TranslationManager\Actions\ExportTranslationEntriesToPoAction`
- `exportTranslationEntriesToXliff: Capell\TranslationManager\Actions\ExportTranslationEntriesToXliffAction`
- `importTranslationEntriesFromCsv: Capell\TranslationManager\Actions\ImportTranslationEntriesFromCsvAction`
- `importTranslationEntriesFromPo: Capell\TranslationManager\Actions\ImportTranslationEntriesFromPoAction`
- `importTranslationEntriesFromXliff: Capell\TranslationManager\Actions\ImportTranslationEntriesFromXliffAction`
- `listInstalledLocales: Capell\TranslationManager\Actions\ListInstalledLocalesAction`
- `listTranslationFiles: Capell\TranslationManager\Actions\ListTranslationFilesAction`
- `listTranslationSources: Capell\TranslationManager\Actions\ListTranslationSourcesAction`
- `loadTranslationComparison: Capell\TranslationManager\Actions\LoadTranslationComparisonAction`
- `saveTranslationEntries: Capell\TranslationManager\Actions\SaveTranslationEntriesAction`
- `scanMissingTranslationKeys: Capell\TranslationManager\Actions\ScanMissingTranslationKeysAction`
- `translateSelectedEntries: Capell\TranslationManager\Actions\TranslateSelectedEntriesAction`

### Manifest contributions

- `admin-page: Capell\TranslationManager\Manifest\TranslationManagerPageContribution`

### Health checks

- `Capell\TranslationManager\Health\TranslationManagerHealthCheck`

### Blade views

- `packages/translation-manager/resources/views/filament/pages/translation-manager.blade.php`


## Data Model

- Required tables: `capell_translation_scan_runs`.
- Models: `TranslationScanRun`.
- Migration files: `2026_07_19_120000_create_translation_scan_runs_table.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: Docs gap: migrations and manifest contributions do not prove a cascade, pruning command, or timed retention policy.

## Install Impact

- Required packages: `capell-app/admin`, `capell-app/core`.
- Admin navigation: declares `admin-page: TranslationManagerPageContribution`; each Filament page or resource controls its own navigation visibility.
- Admin/editor extensions: none declared.
- Permissions: no package permission declarations or Shield gates detected; host access rules still apply.
- Public routes: none declared.
- Database changes: package migrations are declared.
- Config: `config/capell-translation-manager.php`.
- Settings: no package settings declared.
- Queues or schedules: queue jobs `RunTranslationScanJob`.
- Cache tags: none declared.
- Commands: none declared.

## Common Pitfalls

- Keep required Capell packages on compatible v4 releases: `capell-app/admin`, `capell-app/core`.
- Run migrations before opening package resources or public routes.
- Review package configuration before production-like verification: `config/capell-translation-manager.php`.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Admin screen or command fails on missing table | Package migrations have not run | Check the tables listed in `Data Model` | Run host migrations and rerun the focused package test |
| Background work does not run | Queue worker or declared schedule is not active | Check the jobs and scheduled commands listed in `Technical Shape` | Start the queue worker or host scheduler, then run the focused command or package test |

## Quick Start

1. Install the package: `composer require capell-app/translation-manager`.
2. Open `/screenshot-fixtures/marketplace-translation-manager/translation-manager-page-empty-state` and confirm the public output renders without admin state.

## Next Steps

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- [Admin guide](docs/admin-guide.md)
- Configuration files: [`config/capell-translation-manager.php`](config/capell-translation-manager.php).
- [Troubleshooting](#troubleshooting)
- [Screenshot contract](docs/screenshots.json)
- [Marketplace assets](docs/assets/marketplace/)
- [Capell content language plan](../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../docs/erd/capell-and-package-erds.md)
- Related packages: [Ai Orchestrator](../ai-orchestrator/README.md), [Seo Suite](../seo-suite/README.md).
- Focused tests: `vendor/bin/pest packages/translation-manager/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
