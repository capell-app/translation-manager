<?php

declare(strict_types=1);

use Capell\TranslationManager\Actions\BuildLocalePublishReadinessAction;
use Capell\TranslationManager\Actions\CreateLocaleFilesAction;
use Capell\TranslationManager\Actions\DuplicateLocaleAction;
use Capell\TranslationManager\Actions\ExportTranslationEntriesToCsvAction;
use Capell\TranslationManager\Actions\ExportTranslationEntriesToXliffAction;
use Capell\TranslationManager\Actions\ImportTranslationEntriesFromCsvAction;
use Capell\TranslationManager\Actions\ImportTranslationEntriesFromXliffAction;
use Capell\TranslationManager\Actions\LoadTranslationComparisonAction;
use Capell\TranslationManager\Actions\SaveTranslationEntriesAction;
use Capell\TranslationManager\Actions\TranslateSelectedEntriesAction;
use Capell\TranslationManager\Filament\Pages\TranslationManagerPage;
use Capell\TranslationManager\Manifest\TranslationManagerPageContribution;
use Capell\TranslationManager\Providers\AdminServiceProvider;
use Capell\TranslationManager\Providers\TranslationManagerServiceProvider;

it('declares translation workflow capabilities actions and admin page contribution', function (): void {
    $manifest = json_decode(
        (string) file_get_contents(__DIR__ . '/../../capell.json'),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest['description'])->toContain('stale detection')
        ->and($manifest['marketplace']['summary'])->toContain('per-locale publish readiness')
        ->and($manifest['providers']['runtime'])->toContain(TranslationManagerServiceProvider::class)
        ->and($manifest['providers']['admin'])->toContain(AdminServiceProvider::class)
        ->and($manifest['contributes'])->toContain([
            'type' => 'admin-page',
            'class' => TranslationManagerPageContribution::class,
            'pageClass' => TranslationManagerPage::class,
            'labelKey' => 'capell-translation-manager::package.navigation_label',
        ])
        ->and($manifest['actions'])->toHaveKey('buildLocalePublishReadiness', BuildLocalePublishReadinessAction::class)
        ->and($manifest['actions'])->toHaveKey('createLocaleFiles', CreateLocaleFilesAction::class)
        ->and($manifest['actions'])->toHaveKey('duplicateLocale', DuplicateLocaleAction::class)
        ->and($manifest['actions'])->toHaveKey('exportTranslationEntriesToCsv', ExportTranslationEntriesToCsvAction::class)
        ->and($manifest['actions'])->toHaveKey('exportTranslationEntriesToXliff', ExportTranslationEntriesToXliffAction::class)
        ->and($manifest['actions'])->toHaveKey('importTranslationEntriesFromCsv', ImportTranslationEntriesFromCsvAction::class)
        ->and($manifest['actions'])->toHaveKey('importTranslationEntriesFromXliff', ImportTranslationEntriesFromXliffAction::class)
        ->and($manifest['actions'])->toHaveKey('loadTranslationComparison', LoadTranslationComparisonAction::class)
        ->and($manifest['actions'])->toHaveKey('saveTranslationEntries', SaveTranslationEntriesAction::class)
        ->and($manifest['actions'])->toHaveKey('translateSelectedEntries', TranslateSelectedEntriesAction::class)
        ->and($manifest['capabilities'])->toContain(
            'translation.files.csv-import-export',
            'translation.files.publish-readiness',
            'translation.files.stale-detection',
            'translation.files.xliff-import-export',
            'translation.files.write',
        )
        ->and($manifest['contributionTraceability']['deferredContributions'])->not->toContain('admin-page');
});
