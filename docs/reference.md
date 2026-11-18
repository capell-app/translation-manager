# Translation Manager reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/translation-manager`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

Translation Manager is an **Available** Capell package in the **Capell Publishing** product group. It ships as `capell-app/translation-manager` and extends these surfaces: admin.

- Status: Available
- Tier: free
- Bundle: publishing
- Composer package: `capell-app/translation-manager`
- Namespace: `Capell\TranslationManager`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\TranslationManager\Providers\MigrationMetadataServiceProvider`
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
- `EnsureTranslationManagerPermissionsAction`
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
- `TranslationImportData`
- `TranslationLocaleOperationData`
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

<!-- prettier-ignore-end -->
