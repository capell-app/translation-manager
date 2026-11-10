# Translation Manager

<!-- prettier-ignore-start -->

## What It Does

Translation Manager adds an admin workspace for comparing Laravel locales, finding missing or stale entries, and creating, duplicating, importing, exporting, or saving translation files. It works through file storage rather than adding translation tables.

- **Price and availability:** Check current availability and price on the [Capell Marketplace listing](https://capell.app/extensions/translation-manager).
- **Licence:** Check the licence terms on the [Capell Marketplace listing](https://capell.app/extensions/translation-manager).
- **Requires:** `PHP ^8.4`, `capell-app/admin ^1.0.66`, `capell-app/core ^1.0.75`, `lorisleiva/laravel-actions ^2.8`, `spatie/laravel-data ^4.5`, `spatie/laravel-package-tools ^1.14.1`.
- **Install:** `composer require capell-app/translation-manager`.

Administrators can compare locale coverage, edit entries, and manage locale files from one Filament page.

## Who It Is For

**For developers:** A TranslationFileStore contract separates file access from focused comparison and write Actions, so storage behaviour remains testable.

**For teams:** Content and localization teams can close locale gaps while keeping changes in normal Laravel language files and package overrides.

## Quick Start

1. Install the package: `composer require capell-app/translation-manager`.
2. Open the package admin surface at `/admin/translation-manager` and confirm Translation Manager is available.

## Configure

- **Configuration files:** `config/capell-translation-manager.php`.

## Limits And Pitfalls

- Install the required Capell packages at the versions in `composer.json`: `capell-app/admin ^1.0.66`, `capell-app/core ^1.0.75`.
- Run migrations before opening package resources or public routes.

## Screens And Workflow

![Translation Manager work queue](docs/screenshots/translation-manager-page-empty-state.png)

![Translation comparison in the installed Translation Manager](docs/screenshots/translation-manager-comparison-grid.png)

- Translation Manager work queue (admin).
- Translation comparison in the installed Translation Manager (admin).
- Create locale modal (admin).
- Duplicate locale modal (admin).
- Translate a selected missing entry (admin).
- Changed and missing translations in the native review grid (admin).
- Import translations modal (admin).
- Review and accept a translation suggestion (admin).
- Locale publish readiness (admin).

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

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Admin screen or command fails on missing table | Package migrations have not run | Check the tables listed in `Data Model` | Run host migrations and rerun the focused package test |
| Background work does not run | Queue worker or declared schedule is not active | Check the jobs and scheduled commands listed in `Technical Shape` | Start the queue worker or host scheduler, then run the focused command or package test |

## Upgrading

No manual steps between minor versions.

Read [CHANGELOG.md](CHANGELOG.md) before upgrading.

## Next Steps

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- [Worked extension examples](docs/extension-contracts.md)
- [Admin guide](docs/admin-guide.md)
- Configuration files: [`config/capell-translation-manager.php`](config/capell-translation-manager.php).
- [Troubleshooting](#troubleshooting)
- [Developer reference](docs/reference.md)
- [Marketplace assets](docs/assets/marketplace/)
- [Capell content language plan](../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../docs/erd/capell-and-package-erds.md)
- Related packages: [AI Orchestrator](../ai-orchestrator/README.md), [SEO Suite](../seo-suite/README.md).

<!-- prettier-ignore-end -->
