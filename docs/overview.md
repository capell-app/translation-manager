# Translation Manager

<!-- prettier-ignore-start -->

## What This Plugin Adds

Translation Manager is an **Available**, **No schema impact** Capell package in the **Capell Admin** product group. It ships as `capell-app/translation-manager` and extends these surfaces: admin.

Translation Manager gives admins a file-based editor for Laravel language files in your Capell app and installed packages, without migrations or new tables. Compare source and target locales side by side, spot missing and stale keys, and create or duplicate locale files from the admin workflow. Edits to package and vendor strings are written safely to Laravel's override paths, so upgrades never clobber your translations. Pair it with AI Orchestrator for reviewed translation drafts and with SEO Suite when multilingual search teams need translation coverage alongside SEO and AI-discovery workflows.

After install, admins get package-owned management or reporting surfaces inside Capell.

Status details:

- Status: Available
- Tier: premium
- Bundle: admin
- Composer package: `capell-app/translation-manager`
- Namespace: `Capell\TranslationManager`
- Theme key: not applicable

## Why It Matters

**For developers:** The package gives developers package-owned service providers, Actions, Data objects, Filament classes, and Blade views instead of pushing this behaviour into core or application code.

**For teams:** Manage Capell language files from one Filament page with side-by-side locale editing, missing and stale key checks, safe override writes, and optional reviewed AI drafting.

## Screens And Workflow

Screenshot contract: `screenshots.json`.

- Translation Manager page with source, locale, file, and filter selectors in the no-results state (admin, required).
- Translation comparison grid with source strings, editable target strings, statuses, and entry selection (admin, required).
- Create locale modal (admin, required).
- Duplicate locale modal (admin, required).
- Translate selected action visible with AI translator support (admin, optional).

## Screenshot Evidence

These captures are the package-owned visual contract for the admin pages, public pages, actions, workflows, and feature surfaces described above. Keep this section aligned with `docs/screenshots.json` whenever the package surface changes.

### Translation Manager page with source, locale, file, and filter selectors in the no-results state

![Translation Manager page with source, locale, file, and filter selectors in the no-results state](screenshots/translation-manager-page-empty-state.png)

- Surface: admin · Target: /admin/translation-manager.
- Documents: An administrator filters the current translation file to a state with no matching rows and still sees the source, locale, file, and filter controls.
- Capture notes: Capture after installing only the core Capell stack and capell-app/translation-manager, with package_paths restricted to [] in the disposable harness so unrelated local package repositories do not appear as sources.

### Translation comparison grid with source strings, editable target strings, statuses, and entry selection

![Translation comparison grid with source strings, editable target strings, statuses, and entry selection](screenshots/translation-manager-comparison-grid.png)

- Surface: admin · Target: /admin/translation-manager.
- Documents: A translator reviews source strings beside editable target strings, status, and selection controls.
- Capture notes: Seed the application source with an en file and a partial fr file so the grid shows missing and changed entries without relying on another package.

### Create locale modal

![Create locale modal](screenshots/translation-manager-create-locale-modal.png)

- Surface: admin · Target: /admin/translation-manager.
- Documents: A translator opens the Create locale action to add a new target locale.
- Capture notes: Open the Create locale header action from /admin/translation-manager.

### Duplicate locale modal

![Duplicate locale modal](screenshots/translation-manager-duplicate-locale-modal.png)

- Surface: admin · Target: /admin/translation-manager.
- Documents: A translator opens the Duplicate locale action to copy strings from an existing locale.
- Capture notes: Open the Duplicate locale header action from /admin/translation-manager after en and fr locales exist.

### Translate selected action visible with AI translator support

![Translate selected action visible with AI translator support](screenshots/translation-manager-ai-translate-selected.png)

- Surface: admin · Target: /admin/translation-manager.
- Documents: A translator runs or reviews the Translate selected action when an AI translator binding is available.
- Capture notes: Requires an available TranslationAITranslator binding, typically through the optional AI Orchestrator integration.

## Technical Shape

- Service providers: `Capell\TranslationManager\Providers\TranslationManagerServiceProvider`, `Capell\TranslationManager\Providers\AdminServiceProvider`.
- Config files: `packages/translation-manager/config/capell-translation-manager.php`.
- Filament classes: `TranslationManagerPage`.
- Actions: `BuildLocalePublishReadinessAction`, `BuildTranslationMemorySuggestionsAction`, `CreateLocaleFilesAction`, `DuplicateLocaleAction`, `ExportTranslationEntriesToCsvAction`, `ExportTranslationEntriesToPoAction`, `ExportTranslationEntriesToXliffAction`, `ImportTranslationEntriesFromCsvAction`, `ImportTranslationEntriesFromPoAction`, `ImportTranslationEntriesFromXliffAction`, `ListInstalledLocalesAction`, `ListTranslationFilesAction`, `and 5 more`.
- Data objects: `AITranslationSuggestionData`, `LocalePublishReadinessData`, `LocaleSummaryData`, `MissingTranslationKeyData`, `TranslationCsvImportResultData`, `TranslationEntryData`, `TranslationFileData`, `TranslationSourceData`, `TranslationWriteData`.
- Manifest contributions: `admin-page: Capell\TranslationManager\Manifest\TranslationManagerPageContribution`.
- Health checks: `Capell\TranslationManager\Health\TranslationManagerHealthCheck`.
- Blade views: `packages/translation-manager/resources/views/filament/pages/translation-manager.blade.php`.

## Data Model

This package has no schema impact. It does not declare package-owned migrations or required tables.

Docs gap: document extension points here if the package delegates persistence to a host package.

## Install Impact

- Admin navigation: adds package-owned Filament classes when registered.
- Permissions: none declared in `capell.json`.
- Public routes: none detected in package route files.
- Database changes: no package migrations declared.
- Settings: no package settings declared.
- Queues or schedules: none detected in standard package paths.
- Cache tags: none declared.
- Commands: none declared.

## Common Pitfalls

- Verify the package is installed before expecting its provider, views, or extension contributions to run.
- Keep `composer.json`, `composer.local.json`, `capell.json`, docs, screenshots, and tests aligned when the package surface changes.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |

## Quick Start

1. Install the package: `composer require capell-app/translation-manager`.
2. Run the required setup: no package migrations are declared; clear cached config and routes if the host app uses caches.
3. Open the related Capell admin surface and verify Translation Manager appears.

## Next Steps

- [Package docs index](README.md)
- [Screenshot contract](screenshots.json)
- [Marketplace assets](assets/marketplace/)
- [Capell content language plan](../../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../../docs/erd/capell-and-package-erds.md)
- Related packages: [Ai Orchestrator](../../ai-orchestrator/README.md), [Seo Suite](../../seo-suite/README.md).
- Focused tests: `vendor/bin/pest packages/translation-manager/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
