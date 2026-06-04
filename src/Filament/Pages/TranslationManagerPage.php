<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Filament\Pages;

use BackedEnum;
use Capell\Admin\Filament\Pages\ExtensionsPage;
use Capell\TranslationManager\Actions\BuildLocalePublishReadinessAction;
use Capell\TranslationManager\Actions\CreateLocaleFilesAction;
use Capell\TranslationManager\Actions\DuplicateLocaleAction;
use Capell\TranslationManager\Actions\ExportTranslationEntriesToCsvAction;
use Capell\TranslationManager\Actions\ExportTranslationEntriesToPoAction;
use Capell\TranslationManager\Actions\ExportTranslationEntriesToXliffAction;
use Capell\TranslationManager\Actions\ImportTranslationEntriesFromCsvAction;
use Capell\TranslationManager\Actions\ImportTranslationEntriesFromPoAction;
use Capell\TranslationManager\Actions\ImportTranslationEntriesFromXliffAction;
use Capell\TranslationManager\Actions\ListInstalledLocalesAction;
use Capell\TranslationManager\Actions\ListTranslationFilesAction;
use Capell\TranslationManager\Actions\ListTranslationSourcesAction;
use Capell\TranslationManager\Actions\LoadTranslationComparisonAction;
use Capell\TranslationManager\Actions\SaveTranslationEntriesAction;
use Capell\TranslationManager\Actions\ScanMissingTranslationKeysAction;
use Capell\TranslationManager\Actions\TranslateSelectedEntriesAction;
use Capell\TranslationManager\Contracts\TranslationAITranslator;
use Capell\TranslationManager\Data\LocalePublishReadinessData;
use Capell\TranslationManager\Data\LocaleSummaryData;
use Capell\TranslationManager\Data\TranslationEntryData;
use Capell\TranslationManager\Data\TranslationFileData;
use Capell\TranslationManager\Data\TranslationSourceData;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Override;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TranslationManagerPage extends Page
{
    private const string SESSION_SELECTION_KEY = 'capell.translation-manager.selection';

    public ?string $sourceKey = null;

    public string $sourceLocale = 'en';

    public ?string $targetLocale = null;

    public ?string $fileKey = null;

    public string $filter = 'all';

    /** @var array<int, array{key: string, label: string}> */
    public array $sources = [];

    /** @var array<int, array{locale: string, fileCount: int, sourceAvailable: bool, overrideAvailable: bool}> */
    public array $locales = [];

    /** @var array<int, array{key: string, label: string, type: string, relativePath: string}> */
    public array $files = [];

    /** @var array<int, array{index: int, key: string, sourceValue: string|null, targetValue: string|null, status: string, editable: bool}> */
    public array $entries = [];

    /** @var array<int, array{locale: string, fileCount: int, entryCount: int, missing: int, stale: int, changed: int, same: int, extra: int, fallback: int, ready: bool}> */
    public array $readinessMatrix = [];

    /** @var array<int, string> */
    public array $selectedEntryKeys = [];

    /** @var array<string, string> */
    public array $pendingAiSuggestions = [];

    /** @var array<int, array{key: string, path: string, line: int}> */
    public array $missingCodeKeys = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $slug = 'translation-manager';

    protected static ?int $navigationSort = 91;

    protected string $view = 'capell-translation-manager::filament.pages.translation-manager';

    #[Override]
    public static function getNavigationLabel(): string
    {
        return (string) __('capell-translation-manager::package.navigation_label');
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return (string) __('capell-admin::navigation.group_system');
    }

    #[Override]
    public static function canAccess(): bool
    {
        return ExtensionsPage::canManageExtensions();
    }

    #[Override]
    public function getTitle(): string
    {
        return (string) __('capell-translation-manager::package.title');
    }

    public function mount(): void
    {
        $selection = session()->get(self::SESSION_SELECTION_KEY, []);
        $configuredSourceLocale = config('capell-translation-manager.source_locale', 'en');
        $this->sourceLocale = is_array($selection) && is_string($selection['sourceLocale'] ?? null)
            ? $selection['sourceLocale']
            : (is_string($configuredSourceLocale) ? $configuredSourceLocale : 'en');
        $this->targetLocale = is_array($selection) && is_string($selection['targetLocale'] ?? null) ? $selection['targetLocale'] : null;
        $this->fileKey = is_array($selection) && is_string($selection['fileKey'] ?? null) ? $selection['fileKey'] : null;
        $this->filter = is_array($selection) && is_string($selection['filter'] ?? null) ? $selection['filter'] : 'all';

        $this->loadSources();
        $savedSourceKey = is_array($selection) && is_string($selection['sourceKey'] ?? null) ? $selection['sourceKey'] : null;
        $sourceKeys = array_column($this->sources, 'key');
        $this->sourceKey = in_array($savedSourceKey, $sourceKeys, true) ? $savedSourceKey : ($this->sources[0]['key'] ?? null);
        $this->refreshBrowser();
    }

    public function updatedSourceKey(): void
    {
        $this->fileKey = null;
        $this->targetLocale = null;
        $this->refreshBrowser();
    }

    public function updatedSourceLocale(): void
    {
        $this->refreshFiles();
        $this->loadEntries();
        $this->refreshReadinessMatrix();
        $this->rememberSelection();
    }

    public function updatedTargetLocale(): void
    {
        $this->refreshFiles();
        $this->loadEntries();
        $this->rememberSelection();
    }

    public function updatedFileKey(): void
    {
        $this->loadEntries();
        $this->rememberSelection();
    }

    public function updatedFilter(): void
    {
        $this->rememberSelection();
    }

    public function refreshBrowser(): void
    {
        $this->refreshLocales();
        $this->refreshFiles();
        $this->loadEntries();
        $this->refreshReadinessMatrix();
        $this->rememberSelection();
    }

    public function saveTranslations(): void
    {
        if ($this->sourceKey === null || $this->fileKey === null || $this->targetLocale === null) {
            return;
        }

        SaveTranslationEntriesAction::run(
            $this->sourceKey,
            $this->fileKey,
            $this->targetLocale,
            $this->currentEditableTranslationValues(),
        );

        $this->loadEntries();

        Notification::make()
            ->title(__('capell-translation-manager::package.saved'))
            ->success()
            ->send();
    }

    public function acceptAiSuggestion(string $key): void
    {
        if (! array_key_exists($key, $this->pendingAiSuggestions)) {
            return;
        }

        foreach ($this->entries as $index => $entry) {
            if ($entry['key'] !== $key) {
                continue;
            }

            if (! $entry['editable']) {
                continue;
            }

            $this->entries[$index]['targetValue'] = $this->pendingAiSuggestions[$key];
            unset($this->pendingAiSuggestions[$key]);

            return;
        }
    }

    public function rejectAiSuggestion(string $key): void
    {
        unset($this->pendingAiSuggestions[$key]);
    }

    /**
     * @return array<int, array{index: int, key: string, sourceValue: string|null, targetValue: string|null, status: string, editable: bool}>
     */
    public function filteredEntries(): array
    {
        if ($this->filter === 'all') {
            return $this->entries;
        }

        if ($this->filter === 'needs_attention') {
            return collect($this->entries)
                ->filter(fn (array $entry): bool => in_array($entry['status'], ['missing', 'stale', 'changed'], true))
                ->values()
                ->all();
        }

        return collect($this->entries)
            ->filter(fn (array $entry): bool => $entry['status'] === $this->filter)
            ->values()
            ->all();
    }

    public function aiAvailable(): bool
    {
        return resolve(TranslationAITranslator::class)->available();
    }

    /**
     * @return array<string, string>
     */
    public function localeOptions(): array
    {
        return collect($this->locales)
            ->mapWithKeys(fn (array $locale): array => [$locale['locale'] => $locale['locale']])
            ->all();
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createLocale')
                ->label(__('capell-translation-manager::package.create_locale'))
                ->icon(Heroicon::OutlinedPlusCircle)
                ->schema([
                    TextInput::make('locale')
                        ->label(__('capell-translation-manager::package.locale'))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    if ($this->sourceKey === null) {
                        return;
                    }

                    CreateLocaleFilesAction::run($this->sourceKey, (string) $data['locale'], $this->sourceLocale);
                    $this->targetLocale = (string) $data['locale'];
                    $this->refreshBrowser();

                    Notification::make()
                        ->title(__('capell-translation-manager::package.locale_created'))
                        ->success()
                        ->send();
                }),
            Action::make('duplicateLocale')
                ->label(__('capell-translation-manager::package.duplicate_locale'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->schema([
                    Select::make('from_locale')
                        ->label(__('capell-translation-manager::package.from_locale'))
                        ->options(fn (): array => $this->localeOptions())
                        ->required(),
                    TextInput::make('target_locale')
                        ->label(__('capell-translation-manager::package.target_locale'))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    if ($this->sourceKey === null) {
                        return;
                    }

                    DuplicateLocaleAction::run($this->sourceKey, (string) $data['from_locale'], (string) $data['target_locale']);
                    $this->targetLocale = (string) $data['target_locale'];
                    $this->refreshBrowser();

                    Notification::make()
                        ->title(__('capell-translation-manager::package.locale_duplicated'))
                        ->success()
                        ->send();
                }),
            Action::make('translateSelected')
                ->label(__('capell-translation-manager::package.translate_selected'))
                ->icon(Heroicon::OutlinedSparkles)
                ->visible(fn (): bool => $this->aiAvailable())
                ->action(function (): void {
                    $this->translateSelectedEntries();
                }),
            Action::make('exportCsv')
                ->label(__('capell-translation-manager::package.export_csv'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn (): ?StreamedResponse => $this->exportCurrentFile('csv')),
            Action::make('exportXliff')
                ->label(__('capell-translation-manager::package.export_xliff'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn (): ?StreamedResponse => $this->exportCurrentFile('xliff')),
            Action::make('exportPo')
                ->label(__('capell-translation-manager::package.export_po'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn (): ?StreamedResponse => $this->exportCurrentFile('po')),
            Action::make('importTranslations')
                ->label(__('capell-translation-manager::package.import_translations'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->schema([
                    Select::make('format')
                        ->label(__('capell-translation-manager::package.import_format'))
                        ->options([
                            'csv' => __('capell-translation-manager::package.format_csv'),
                            'xliff' => __('capell-translation-manager::package.format_xliff'),
                            'po' => __('capell-translation-manager::package.format_po'),
                        ])
                        ->default('csv')
                        ->required(),
                    Textarea::make('contents')
                        ->label(__('capell-translation-manager::package.import_contents'))
                        ->rows(12)
                        ->required()
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $this->importCurrentFile($data);
                }),
            Action::make('publishReadiness')
                ->label(__('capell-translation-manager::package.publish_readiness'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->action(function (): void {
                    $this->notifyCurrentPublishReadiness();
                }),
            Action::make('scanMissingKeys')
                ->label(__('capell-translation-manager::package.scan_missing_keys'))
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->action(function (): void {
                    $this->scanMissingCodeKeys();
                }),
            Action::make('saveTranslations')
                ->label(__('capell-translation-manager::package.save'))
                ->icon(Heroicon::OutlinedCloudArrowUp)
                ->action(function (): void {
                    $this->saveTranslations();
                }),
        ];
    }

    private function loadSources(): void
    {
        $this->sources = array_values(array_map(
            fn (TranslationSourceData $source): array => [
                'key' => $source->key,
                'label' => $source->label,
            ],
            resolve(ListTranslationSourcesAction::class)->handle(),
        ));
    }

    private function refreshLocales(): void
    {
        if ($this->sourceKey === null) {
            $this->locales = [];

            return;
        }

        $this->locales = array_values(array_map(
            fn (LocaleSummaryData $locale): array => [
                'locale' => $locale->locale,
                'fileCount' => $locale->fileCount,
                'sourceAvailable' => $locale->sourceAvailable,
                'overrideAvailable' => $locale->overrideAvailable,
            ],
            resolve(ListInstalledLocalesAction::class)->handle($this->sourceKey),
        ));

        $localeNames = array_column($this->locales, 'locale');

        if ($this->targetLocale === null || ! in_array($this->targetLocale, $localeNames, true)) {
            $this->targetLocale = $this->firstDifferentLocale($localeNames) ?? $this->sourceLocale;
        }
    }

    private function refreshFiles(): void
    {
        if ($this->sourceKey === null || $this->targetLocale === null) {
            $this->files = [];
            $this->fileKey = null;

            return;
        }

        $this->files = array_values(array_map(
            fn (TranslationFileData $file): array => [
                'key' => $file->key,
                'label' => $file->label,
                'type' => $file->type,
                'relativePath' => $file->relativePath,
            ],
            resolve(ListTranslationFilesAction::class)->handle($this->sourceKey, $this->sourceLocale, $this->targetLocale),
        ));

        $fileKeys = array_column($this->files, 'key');

        if ($this->fileKey === null || ! in_array($this->fileKey, $fileKeys, true)) {
            $this->fileKey = $this->files[0]['key'] ?? null;
        }
    }

    private function loadEntries(): void
    {
        $this->selectedEntryKeys = [];
        $this->pendingAiSuggestions = [];

        if ($this->sourceKey === null || $this->fileKey === null || $this->targetLocale === null) {
            $this->entries = [];

            return;
        }

        $this->entries = collect(resolve(LoadTranslationComparisonAction::class)->handle($this->sourceKey, $this->fileKey, $this->sourceLocale, $this->targetLocale))
            ->map(fn (TranslationEntryData $entry, int $index): array => [
                'index' => $index,
                'key' => $entry->key,
                'sourceValue' => $entry->sourceValue,
                'targetValue' => $entry->targetValue,
                'status' => $entry->status,
                'editable' => $entry->editable,
            ])
            ->values()
            ->all();
    }

    private function refreshReadinessMatrix(): void
    {
        if ($this->sourceKey === null || $this->locales === []) {
            $this->readinessMatrix = [];

            return;
        }

        $this->readinessMatrix = collect($this->locales)
            ->pluck('locale')
            ->filter(fn (string $locale): bool => $locale !== $this->sourceLocale)
            ->map(fn (string $locale): array => $this->readinessRow(BuildLocalePublishReadinessAction::run(
                $this->sourceKey,
                $this->sourceLocale,
                $locale,
            )))
            ->values()
            ->all();
    }

    /**
     * @return array{locale: string, fileCount: int, entryCount: int, missing: int, stale: int, changed: int, same: int, extra: int, fallback: int, ready: bool}
     */
    private function readinessRow(LocalePublishReadinessData $readiness): array
    {
        return [
            'locale' => $readiness->targetLocale,
            'fileCount' => $readiness->fileCount,
            'entryCount' => $readiness->entryCount,
            'missing' => $readiness->statusCounts['missing'] ?? 0,
            'stale' => $readiness->statusCounts['stale'] ?? 0,
            'changed' => $readiness->statusCounts['changed'] ?? 0,
            'same' => $readiness->statusCounts['same'] ?? 0,
            'extra' => $readiness->statusCounts['extra'] ?? 0,
            'fallback' => $readiness->statusCounts['fallback'] ?? 0,
            'ready' => $readiness->ready,
        ];
    }

    private function exportCurrentFile(string $format): ?StreamedResponse
    {
        if ($this->sourceKey === null || $this->fileKey === null || $this->targetLocale === null) {
            return null;
        }

        $contents = match ($format) {
            'xliff' => ExportTranslationEntriesToXliffAction::run($this->sourceKey, $this->fileKey, $this->sourceLocale, $this->targetLocale),
            'po' => ExportTranslationEntriesToPoAction::run($this->sourceKey, $this->fileKey, $this->sourceLocale, $this->targetLocale),
            default => ExportTranslationEntriesToCsvAction::run($this->sourceKey, $this->fileKey, $this->sourceLocale, $this->targetLocale),
        };

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $this->downloadFilename($format),
            ['Content-Type' => match ($format) {
                'xliff' => 'application/x-xliff+xml',
                'po' => 'text/x-gettext-translation',
                default => 'text/csv',
            }],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importCurrentFile(array $data): void
    {
        if ($this->sourceKey === null || $this->fileKey === null || $this->targetLocale === null) {
            return;
        }

        $contents = (string) ($data['contents'] ?? '');
        $format = (string) ($data['format'] ?? 'csv');
        $result = match ($format) {
            'xliff' => ImportTranslationEntriesFromXliffAction::run($this->sourceKey, $this->fileKey, $this->targetLocale, $contents),
            'po' => ImportTranslationEntriesFromPoAction::run($this->sourceKey, $this->fileKey, $this->targetLocale, $contents),
            default => ImportTranslationEntriesFromCsvAction::run($this->sourceKey, $this->fileKey, $this->targetLocale, $contents),
        };

        $this->refreshBrowser();

        Notification::make()
            ->title(__('capell-translation-manager::package.imported', [
                'imported' => $result->importedCount,
                'skipped' => $result->skippedCount,
            ]))
            ->success()
            ->send();
    }

    private function notifyCurrentPublishReadiness(): void
    {
        if ($this->sourceKey === null || $this->targetLocale === null) {
            return;
        }

        $readiness = BuildLocalePublishReadinessAction::run($this->sourceKey, $this->sourceLocale, $this->targetLocale);

        Notification::make()
            ->title($readiness->ready
                ? __('capell-translation-manager::package.publish_ready')
                : __('capell-translation-manager::package.publish_not_ready'))
            ->body(__('capell-translation-manager::package.publish_readiness_body', [
                'files' => $readiness->fileCount,
                'entries' => $readiness->entryCount,
                'missing' => $readiness->statusCounts['missing'] ?? 0,
                'stale' => $readiness->statusCounts['stale'] ?? 0,
            ]))
            ->status($readiness->ready ? 'success' : 'warning')
            ->send();
    }

    private function scanMissingCodeKeys(): void
    {
        if ($this->sourceKey === null) {
            return;
        }

        $this->missingCodeKeys = array_map(
            static fn ($missingKey): array => [
                'key' => $missingKey->key,
                'path' => $missingKey->path,
                'line' => $missingKey->line,
            ],
            ScanMissingTranslationKeysAction::run($this->sourceKey, $this->sourceLocale),
        );

        Notification::make()
            ->title(__('capell-translation-manager::package.missing_key_scan_complete', [
                'count' => count($this->missingCodeKeys),
            ]))
            ->status($this->missingCodeKeys === [] ? 'success' : 'warning')
            ->send();
    }

    private function downloadFilename(string $format): string
    {
        return str($this->sourceKey . '-' . $this->sourceLocale . '-' . $this->targetLocale . '-' . $this->fileKey)
            ->replace([':', '/', '\\'], '-')
            ->append('.' . match ($format) {
                'xliff' => 'xlf',
                'po' => 'po',
                default => 'csv',
            })
            ->toString();
    }

    private function translateSelectedEntries(): void
    {
        if ($this->targetLocale === null) {
            return;
        }

        $entryData = array_values(array_map(
            fn (array $entry): TranslationEntryData => new TranslationEntryData(
                key: $entry['key'],
                sourceValue: is_string($entry['sourceValue']) ? $entry['sourceValue'] : null,
                targetValue: is_string($entry['targetValue']) ? $entry['targetValue'] : null,
                status: $entry['status'],
                editable: $entry['editable'],
            ),
            $this->entries,
        ));

        $suggestions = resolve(TranslateSelectedEntriesAction::class)->handle($this->sourceLocale, $this->targetLocale, $entryData, $this->selectedEntryKeys, $this->sourceKey);
        $suggestionsByKey = [];

        foreach ($suggestions as $suggestion) {
            $suggestionsByKey[$suggestion->key] = $suggestion->value;
        }

        $this->pendingAiSuggestions = $suggestionsByKey;

        Notification::make()
            ->title(__('capell-translation-manager::package.translated'))
            ->success()
            ->send();
    }

    /**
     * @return array<string, string|null>
     */
    private function currentEditableTranslationValues(): array
    {
        if ($this->sourceKey === null || $this->fileKey === null || $this->targetLocale === null) {
            return [];
        }

        $submittedEntries = collect($this->entries)
            ->keyBy(fn (array $entry): string => $entry['key']);

        return collect(resolve(LoadTranslationComparisonAction::class)->handle(
            $this->sourceKey,
            $this->fileKey,
            $this->sourceLocale,
            $this->targetLocale,
        ))
            ->filter(fn (TranslationEntryData $entry): bool => $entry->editable)
            ->mapWithKeys(function (TranslationEntryData $entry) use ($submittedEntries): array {
                $submittedEntry = $submittedEntries->get($entry->key);
                $submittedValue = is_array($submittedEntry) && array_key_exists('targetValue', $submittedEntry)
                    ? $submittedEntry['targetValue']
                    : $entry->targetValue;

                return [$entry->key => is_string($submittedValue) ? $submittedValue : null];
            })
            ->all();
    }

    private function rememberSelection(): void
    {
        session()->put(self::SESSION_SELECTION_KEY, [
            'sourceKey' => $this->sourceKey,
            'sourceLocale' => $this->sourceLocale,
            'targetLocale' => $this->targetLocale,
            'fileKey' => $this->fileKey,
            'filter' => $this->filter,
        ]);
    }

    /**
     * @param  array<int, string>  $locales
     */
    private function firstDifferentLocale(array $locales): ?string
    {
        foreach ($locales as $locale) {
            if ($locale !== $this->sourceLocale) {
                return $locale;
            }
        }

        return null;
    }
}
