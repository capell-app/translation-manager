<?php

declare(strict_types=1);

use Capell\TranslationManager\Actions\BuildLocalePublishReadinessAction;
use Capell\TranslationManager\Actions\BuildTranslationMemorySuggestionsAction;
use Capell\TranslationManager\Actions\CreateLocaleFilesAction;
use Capell\TranslationManager\Actions\DuplicateLocaleAction;
use Capell\TranslationManager\Actions\ExportTranslationEntriesToCsvAction;
use Capell\TranslationManager\Actions\ExportTranslationEntriesToPoAction;
use Capell\TranslationManager\Actions\ExportTranslationEntriesToXliffAction;
use Capell\TranslationManager\Actions\ImportTranslationEntriesFromCsvAction;
use Capell\TranslationManager\Actions\ImportTranslationEntriesFromPoAction;
use Capell\TranslationManager\Actions\ImportTranslationEntriesFromXliffAction;
use Capell\TranslationManager\Actions\LoadTranslationComparisonAction;
use Capell\TranslationManager\Actions\SaveTranslationEntriesAction;
use Capell\TranslationManager\Actions\ScanMissingTranslationKeysAction;
use Capell\TranslationManager\Actions\TranslateSelectedEntriesAction;
use Capell\TranslationManager\Filament\Pages\TranslationManagerPage;
use Capell\TranslationManager\Manifest\TranslationManagerPageContribution;
use Capell\TranslationManager\Providers\AdminServiceProvider;
use Capell\TranslationManager\Providers\TranslationManagerServiceProvider;

it('declares translation workflow capabilities actions and admin page contribution', function (): void {
    $manifest = capell_json_file_array(__DIR__ . '/../../capell.json');
    assertCapellPackageDescription('translation-manager', $manifest);
    $composer = capell_json_file_array(__DIR__ . '/../../composer.json');
    $screenshots = data_get($manifest, 'marketplace.screenshots', []);

    throw_unless(is_array($screenshots), RuntimeException::class, 'Translation Manager screenshots must be an array.');

    expect(data_get($manifest, 'dependencies.supports'))->toContain('capell-app/ai-orchestrator')
        ->and(data_get($manifest, 'dependencies.supports'))->not->toContain('capell-app/seo-suite')
        ->and(data_get($manifest, 'permissions'))->toBe(['View:TranslationManagerPage', 'Manage:TranslationManagerPage'])
        ->and(data_get($manifest, 'marketplace.summary'))->toBeString()->not->toBeEmpty()
        ->and(data_get($manifest, 'marketplace.description'))->toBeString()->not->toBeEmpty()
        ->and($composer['description'])->toBeString()->not->toBeEmpty()
        ->and(data_get($composer, 'suggest'))->toHaveKey('capell-app/ai-orchestrator', 'Enable optional reviewed AI translation drafts.')
        ->and(data_get($manifest, 'performance.adminQueryBudget'))->toBe(0)
        ->and(data_get($manifest, 'healthChecks.0.label'))->toBe('Translation Manager diagnostics verify the admin surface, service bindings, and safe filesystem configuration.')
        ->and(data_get($manifest, 'providers.runtime'))->toContain(TranslationManagerServiceProvider::class)
        ->and(data_get($manifest, 'providers.admin'))->toContain(AdminServiceProvider::class)
        ->and(data_get($manifest, 'contributes'))->toContain([
            'type' => 'admin-page',
            'class' => TranslationManagerPageContribution::class,
            'pageClass' => TranslationManagerPage::class,
            'labelKey' => 'capell-translation-manager::package.navigation_label',
        ])
        ->and(data_get($manifest, 'actions'))->toHaveKey('buildLocalePublishReadiness', BuildLocalePublishReadinessAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('buildTranslationMemorySuggestions', BuildTranslationMemorySuggestionsAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('createLocaleFiles', CreateLocaleFilesAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('duplicateLocale', DuplicateLocaleAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('exportTranslationEntriesToCsv', ExportTranslationEntriesToCsvAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('exportTranslationEntriesToPo', ExportTranslationEntriesToPoAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('exportTranslationEntriesToXliff', ExportTranslationEntriesToXliffAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('importTranslationEntriesFromCsv', ImportTranslationEntriesFromCsvAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('importTranslationEntriesFromPo', ImportTranslationEntriesFromPoAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('importTranslationEntriesFromXliff', ImportTranslationEntriesFromXliffAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('loadTranslationComparison', LoadTranslationComparisonAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('saveTranslationEntries', SaveTranslationEntriesAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('scanMissingTranslationKeys', ScanMissingTranslationKeysAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('translateSelectedEntries', TranslateSelectedEntriesAction::class)
        ->and(data_get($manifest, 'capabilities'))->toBe([
            'translation.ai.translate-selected',
            'translation.files.browse',
            'translation.files.duplicate',
            'translation.files.write',
        ])
        ->and($screenshots)->toHaveCount(6)
        ->and(array_column($screenshots, 'path'))->toContain(
            'docs/screenshots/translation-manager-page-empty-state.png',
            'docs/screenshots/translation-manager-comparison-grid.png',
            'docs/screenshots/translation-manager-create-locale-modal.png',
            'docs/screenshots/translation-manager-duplicate-locale-modal.png',
            'docs/screenshots/translation-manager-ai-translate-selected.png',
        )
        ->and(data_get($manifest, 'contributionTraceability.deferredContributions'))->not->toContain('admin-page');
});
