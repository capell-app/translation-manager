# Translation Manager

<!-- prettier-ignore-start -->

## What This Plugin Adds

Translation Manager is an **Available**, **No schema impact** Capell package in the **Capell Publishing** product group. It ships as `capell-app/translation-manager` and extends these surfaces: admin.

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

![Translation Manager page with source, locale, file, and filter selectors in the no-results state](docs/screenshots/translation-manager-page-empty-state.png)

![Translation comparison grid with source strings, editable target strings, statuses, and entry selection](docs/screenshots/translation-manager-comparison-grid.png)

- Translation Manager page with source, locale, file, and filter selectors in the no-results state (admin, required).
- Translation comparison grid with source strings, editable target strings, statuses, and entry selection (admin, required).
- Create locale modal (admin, required).
- Duplicate locale modal (admin, required).
- Translate selected action visible with AI translator support (admin, optional).
- Dirty and missing translation review state (admin, optional).
- Translation import and export actions (admin, optional).
- AI translation suggestion review (admin, optional).
- Locale publish readiness summary (admin, optional).

## Technical Shape

- Service providers: `Capell\TranslationManager\Providers\TranslationManagerServiceProvider`, `Capell\TranslationManager\Providers\AdminServiceProvider`.
- Config files: `packages/translation-manager/config/capell-translation-manager.php`.
- Filament classes: `TranslationManagerPage`.
- Extension contracts: `TranslationAITranslator`, `TranslationFileStore`, `TranslationSourceResolver`.
- Actions: `BuildLocalePublishReadinessAction`, `BuildTranslationMemorySuggestionsAction`, `BuildTranslationReadinessMatrixAction`, `CreateLocaleFilesAction`, `DuplicateLocaleAction`, `ExportTranslationEntriesToCsvAction`, `ExportTranslationEntriesToPoAction`, `ExportTranslationEntriesToXliffAction`, `FilterTranslationEntriesAction`, `ImportTranslationEntriesFromCsvAction`, `ImportTranslationEntriesFromPoAction`, `ImportTranslationEntriesFromXliffAction`, `and 7 more`.
- Data objects: `AITranslationSuggestionData`, `LocalePublishReadinessData`, `LocaleSummaryData`, `MissingTranslationKeyData`, `TranslationCsvImportResultData`, `TranslationEntryData`, `TranslationFileData`, `TranslationSourceData`, `TranslationWriteData`.
- Manifest action API: `buildLocalePublishReadiness: Capell\TranslationManager\Actions\BuildLocalePublishReadinessAction`, `buildTranslationMemorySuggestions: Capell\TranslationManager\Actions\BuildTranslationMemorySuggestionsAction`, `createLocaleFiles: Capell\TranslationManager\Actions\CreateLocaleFilesAction`, `duplicateLocale: Capell\TranslationManager\Actions\DuplicateLocaleAction`, `exportTranslationEntriesToCsv: Capell\TranslationManager\Actions\ExportTranslationEntriesToCsvAction`, `exportTranslationEntriesToPo: Capell\TranslationManager\Actions\ExportTranslationEntriesToPoAction`, `exportTranslationEntriesToXliff: Capell\TranslationManager\Actions\ExportTranslationEntriesToXliffAction`, `importTranslationEntriesFromCsv: Capell\TranslationManager\Actions\ImportTranslationEntriesFromCsvAction`, `importTranslationEntriesFromPo: Capell\TranslationManager\Actions\ImportTranslationEntriesFromPoAction`, `importTranslationEntriesFromXliff: Capell\TranslationManager\Actions\ImportTranslationEntriesFromXliffAction`, `listInstalledLocales: Capell\TranslationManager\Actions\ListInstalledLocalesAction`, `listTranslationFiles: Capell\TranslationManager\Actions\ListTranslationFilesAction`, `and 5 more`.
- Manifest contributions: `admin-page: Capell\TranslationManager\Manifest\TranslationManagerPageContribution`.
- Health checks: `Capell\TranslationManager\Health\TranslationManagerHealthCheck`.
- Blade views: `packages/translation-manager/resources/views/filament/pages/translation-manager.blade.php`.

## Data Model

This package has no schema impact. It extends Capell through `admin-page` contributions instead of declaring package-owned tables.

## Install Impact

- Required packages: `capell-app/admin`, `capell-app/core`.
- Admin navigation: declares `admin-page: TranslationManagerPageContribution`; each Filament page or resource controls its own navigation visibility.
- Admin/editor extensions: none declared.
- Permissions: none declared in `capell.json`.
- Public routes: none declared.
- Database changes: no package migrations declared.
- Config: `config/capell-translation-manager.php`.
- Settings: no package settings declared.
- Queues or schedules: none declared.
- Cache tags: none declared.
- Commands: none declared.

## Common Pitfalls

- Keep required Capell packages on compatible v4 releases: `capell-app/admin`, `capell-app/core`.
- Review package configuration before production-like verification: `config/capell-translation-manager.php`.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |

## Quick Start

1. Install the package: `composer require capell-app/translation-manager`.
2. Review `config/capell-translation-manager.php` before enabling the package.
3. Open the Translation Manager page with source, locale, file, and filter selectors in the no-results state and confirm the admin workflow loads.

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
