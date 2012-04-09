<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Support;

use Capell\TranslationManager\Contracts\TranslationFileStore;
use Capell\TranslationManager\Data\LocaleSummaryData;
use Capell\TranslationManager\Data\TranslationCsvImportResultData;
use Capell\TranslationManager\Data\TranslationEntryData;
use Capell\TranslationManager\Data\TranslationFileData;
use Capell\TranslationManager\Data\TranslationSourceData;
use Capell\TranslationManager\Data\TranslationWriteData;
use Capell\TranslationManager\Exceptions\TranslationFileWriteException;
use Closure;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final class FileTranslationFileStore implements TranslationFileStore
{
    /**
     * @var array<string, array<int, LocaleSummaryData>>
     */
    private array $localesCache = [];

    /**
     * @var array<string, array<int, TranslationFileData>>
     */
    private array $filesCache = [];

    /**
     * @var array<string, array<int, TranslationEntryData>>
     */
    private array $comparisonCache = [];

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly LocaleValidator $localeValidator,
    ) {}

    public function locales(TranslationSourceData $source): array
    {
        $cacheKey = $this->sourceCacheKey($source);

        if (array_key_exists($cacheKey, $this->localesCache)) {
            return $this->localesCache[$cacheKey];
        }

        $locales = [];

        foreach ([$source->sourcePath, $source->overridePath] as $path) {
            if (! $this->filesystem->isDirectory($path)) {
                continue;
            }

            foreach ($this->filesystem->directories($path) as $localePath) {
                $locale = basename((string) $localePath);

                if (! $this->localeValidator->isValid($locale)) {
                    continue;
                }

                $locales[$locale] ??= [
                    'locale' => $locale,
                    'source' => false,
                    'override' => false,
                ];

                if (str_starts_with((string) $localePath, $source->sourcePath)) {
                    $locales[$locale]['source'] = true;
                }

                if (str_starts_with((string) $localePath, $source->overridePath)) {
                    $locales[$locale]['override'] = true;
                }
            }

            $jsonPaths = glob($path . '/*.json');

            foreach (is_array($jsonPaths) ? $jsonPaths : [] as $jsonPath) {
                $locale = basename($jsonPath, '.json');

                if (! $this->localeValidator->isValid($locale)) {
                    continue;
                }

                $locales[$locale] ??= [
                    'locale' => $locale,
                    'source' => false,
                    'override' => false,
                ];

                if (str_starts_with($jsonPath, $source->sourcePath)) {
                    $locales[$locale]['source'] = true;
                }

                if (str_starts_with($jsonPath, $source->overridePath)) {
                    $locales[$locale]['override'] = true;
                }
            }
        }

        $summaries = collect($locales)
            ->map(fn (array $locale): LocaleSummaryData => new LocaleSummaryData(
                locale: $locale['locale'],
                fileCount: count($this->files($source, $locale['locale'], $locale['locale'])),
                sourceAvailable: $locale['source'],
                overrideAvailable: $locale['override'],
            ))
            ->sortBy(fn (LocaleSummaryData $locale): string => $locale->locale)
            ->values()
            ->all();

        return $this->localesCache[$cacheKey] = $summaries;
    }

    public function files(TranslationSourceData $source, string $sourceLocale, string $targetLocale): array
    {
        $this->localeValidator->assertValid($sourceLocale);
        $this->localeValidator->assertValid($targetLocale);

        $cacheKey = $this->filesCacheKey($source, $sourceLocale, $targetLocale);

        if (array_key_exists($cacheKey, $this->filesCache)) {
            return $this->filesCache[$cacheKey];
        }

        $files = [];

        foreach ([$sourceLocale, $targetLocale] as $locale) {
            foreach ([$source->sourcePath, $source->overridePath] as $basePath) {
                $localePath = $basePath . '/' . $locale;

                if (! $this->filesystem->isDirectory($localePath)) {
                    continue;
                }

                foreach ($this->filesystem->allFiles($localePath) as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }

                    $relativePath = str_replace('\\', '/', $file->getRelativePathname());
                    $name = substr($relativePath, 0, -4);
                    $files['php:' . $name] = new TranslationFileData(
                        key: 'php:' . $name,
                        label: $relativePath,
                        type: 'php',
                        relativePath: $relativePath,
                    );
                }
            }

            foreach ([$source->sourcePath, $source->overridePath] as $basePath) {
                if ($this->filesystem->exists($basePath . '/' . $locale . '.json')) {
                    $files['json'] = new TranslationFileData(
                        key: 'json',
                        label: (string) __('capell-translation-manager::package.json_translations'),
                        type: 'json',
                        relativePath: $locale . '.json',
                    );
                }
            }
        }

        $translationFiles = collect($files)
            ->sortBy(fn (TranslationFileData $file): string => $file->label)
            ->values()
            ->all();

        return $this->filesCache[$cacheKey] = $translationFiles;
    }

    public function comparison(TranslationSourceData $source, string $fileKey, string $sourceLocale, string $targetLocale): array
    {
        $this->localeValidator->assertValid($sourceLocale);
        $this->localeValidator->assertValid($targetLocale);
        $path = $this->path($source, $fileKey, $targetLocale, true);

        return $this->withExclusiveWriteLock($path, function () use ($fileKey, $path, $source, $sourceLocale, $targetLocale): array {
            $this->recoverInterruptedPublication($path);

            return $this->comparisonWhileLocked($source, $fileKey, $sourceLocale, $targetLocale);
        });
    }

    public function createLocale(TranslationSourceData $source, string $locale, string $sourceLocale): void
    {
        $this->localeValidator->assertValid($locale);

        foreach ($this->files($source, $sourceLocale, $sourceLocale) as $file) {
            $sourceValues = TranslationArray::flattenStrings($this->read($source, $file->key, $sourceLocale, false));
            $blankValues = array_fill_keys(array_keys($sourceValues), '');

            $this->write(new TranslationWriteData(
                source: $source,
                fileKey: $file->key,
                locale: $locale,
                values: $blankValues,
            ));
        }
    }

    public function duplicateLocale(TranslationSourceData $source, string $fromLocale, string $targetLocale): void
    {
        $this->localeValidator->assertValid($fromLocale);
        $this->localeValidator->assertValid($targetLocale);

        foreach ($this->files($source, $fromLocale, $fromLocale) as $file) {
            $values = TranslationArray::flattenStrings($this->read($source, $file->key, $fromLocale, false));

            $this->write(new TranslationWriteData(
                source: $source,
                fileKey: $file->key,
                locale: $targetLocale,
                values: $values,
            ));
        }
    }

    public function write(TranslationWriteData $write): void
    {
        $this->localeValidator->assertValid($write->locale);
        $this->assertTranslationIntegrity($write);
        $path = $this->path($write->source, $write->fileKey, $write->locale, true);

        $this->withExclusiveWriteLock($path, function () use ($path, $write): void {
            $this->recoverInterruptedPublication($path);
            $currentValues = $this->read($write->source, $write->fileKey, $write->locale, true);

            foreach ($write->values as $key => $value) {
                if ($write->fileKey === 'json') {
                    $currentValues[$key] = $value ?? '';

                    continue;
                }

                $currentValues = TranslationArray::setNestedValue($currentValues, $key, $value ?? '');
            }

            /** @var non-empty-list<array{path: string, contents: string, values: array<string, mixed>, type: 'json'|'php'}> $artifacts */
            $artifacts = [$this->translationArtifact($write->source, $write->fileKey, $write->locale, $currentValues)];
            $metadataArtifact = $this->sourceHashMetadataArtifact($write);

            if ($metadataArtifact !== null) {
                $artifacts[] = $metadataArtifact;
            }

            $this->publishArtifactsAtomically($artifacts);
        });
        $this->flushSourceCache($write->source);
    }

    public function exportCsv(TranslationSourceData $source, string $fileKey, string $sourceLocale, string $targetLocale): string
    {
        $stream = fopen('php://temp', 'r+');

        throw_if($stream === false, InvalidArgumentException::class, 'Unable to open temporary translation CSV stream.');

        try {
            fputcsv($stream, ['key', 'source_value', 'target_value', 'status'], escape: '\\');

            foreach ($this->comparison($source, $fileKey, $sourceLocale, $targetLocale) as $entry) {
                fputcsv($stream, [
                    $entry->key,
                    $entry->sourceValue ?? '',
                    $entry->targetValue ?? '',
                    $entry->status,
                ], escape: '\\');
            }

            rewind($stream);
            $contents = stream_get_contents($stream);

            return $contents === false ? '' : $contents;
        } finally {
            fclose($stream);
        }
    }

    public function importCsv(TranslationSourceData $source, string $fileKey, string $locale, string $contents): TranslationCsvImportResultData
    {
        $stream = fopen('php://temp', 'r+');

        throw_if($stream === false, InvalidArgumentException::class, 'Unable to open temporary translation CSV stream.');

        try {
            fwrite($stream, $contents);
            rewind($stream);

            $headers = $this->readCsvHeaders($stream);
            $keyIndex = array_search('key', $headers, true);
            $targetValueIndex = array_search('target_value', $headers, true);

            throw_if(! is_int($keyIndex) || ! is_int($targetValueIndex), InvalidArgumentException::class, 'Translation CSV must contain key and target_value columns.');

            $values = [];
            $skippedCount = 0;

            while (($row = fgetcsv($stream, escape: '\\')) !== false) {
                $key = $this->csvCell($row, $keyIndex);

                if ($key === '') {
                    $skippedCount++;

                    continue;
                }

                $values[$key] = $this->csvCell($row, $targetValueIndex);
            }

            $this->write(new TranslationWriteData(
                source: $source,
                fileKey: $fileKey,
                locale: $locale,
                values: $values,
            ));

            return new TranslationCsvImportResultData(
                importedCount: count($values),
                skippedCount: $skippedCount,
            );
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return array<int, TranslationEntryData>
     */
    private function comparisonWhileLocked(TranslationSourceData $source, string $fileKey, string $sourceLocale, string $targetLocale): array
    {
        $cacheKey = $this->comparisonCacheKey($source, $fileKey, $sourceLocale, $targetLocale);

        if (array_key_exists($cacheKey, $this->comparisonCache)) {
            return $this->comparisonCache[$cacheKey];
        }

        $fallbackLocale = $this->fallbackLocale($sourceLocale, $targetLocale);
        $sourceEntries = TranslationArray::flattenForEditor($this->read($source, $fileKey, $sourceLocale, false));
        $targetEntries = TranslationArray::flattenForEditor($this->read($source, $fileKey, $targetLocale, false));
        $fallbackEntries = $fallbackLocale === null ? [] : TranslationArray::flattenForEditor($this->read($source, $fileKey, $fallbackLocale, false));
        $targetSourceHashes = $this->readSourceHashMetadata($source, $fileKey, $targetLocale);
        $sourceModifiedAt = $this->fileModifiedAt($source, $fileKey, $sourceLocale);
        $targetModifiedAt = $this->fileModifiedAt($source, $fileKey, $targetLocale);
        $keys = collect([...array_keys($sourceEntries), ...array_keys($targetEntries)])->unique()->sort()->values();

        $entries = $keys
            ->map(function (string $key) use ($sourceEntries, $targetEntries, $fallbackEntries, $targetSourceHashes, $sourceModifiedAt, $targetModifiedAt): TranslationEntryData {
                $sourceEntry = $sourceEntries[$key] ?? ['value' => null, 'editable' => false, 'exists' => false];
                $targetEntry = $targetEntries[$key] ?? [
                    'value' => null,
                    'editable' => $sourceEntry['editable'],
                    'exists' => false,
                ];
                $fallbackEntry = $fallbackEntries[$key] ?? ['value' => null, 'editable' => false, 'exists' => false];
                $sourceValue = $sourceEntry['value'];
                $targetValue = $targetEntry['value'];
                $sourceExists = $sourceEntry['exists'];
                $targetExists = $targetEntry['exists'];
                $fallbackValue = $fallbackEntry['value'];

                return new TranslationEntryData(
                    key: $key,
                    sourceValue: is_string($sourceValue) ? $sourceValue : null,
                    targetValue: is_string($targetValue) ? $targetValue : null,
                    status: $this->status(
                        sourceExists: $sourceExists,
                        targetExists: $targetExists,
                        fallbackExists: $fallbackEntry['exists'],
                        sourceValue: is_string($sourceValue) ? $sourceValue : null,
                        targetValue: is_string($targetValue) ? $targetValue : null,
                        fallbackValue: is_string($fallbackValue) ? $fallbackValue : null,
                        sourceModifiedAt: $sourceModifiedAt,
                        targetModifiedAt: $targetModifiedAt,
                        sourceHash: is_string($sourceValue) ? $this->sourceHash($sourceValue) : null,
                        translatedSourceHash: $targetSourceHashes[$key] ?? null,
                    ),
                    editable: $sourceEntry['editable'] || $targetEntry['editable'],
                );
            })
            ->all();

        return $this->comparisonCache[$cacheKey] = $entries;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function withExclusiveWriteLock(string $path, Closure $callback): mixed
    {
        $this->filesystem->ensureDirectoryExists(dirname($path));
        $lockPath = storage_path('framework/cache/capell-translation-manager/locks/' . hash('sha256', $path) . '.lock');
        $this->filesystem->ensureDirectoryExists(dirname($lockPath));

        try {
            $lock = fopen($lockPath, 'c+b');
        } catch (Throwable $exception) {
            throw TranslationFileWriteException::lockFailed($path, $exception);
        }

        if ($lock === false) {
            throw TranslationFileWriteException::lockFailed($path);
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw TranslationFileWriteException::lockFailed($path);
            }

            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function assertTranslationIntegrity(TranslationWriteData $write): void
    {
        $sourceLocale = config('capell-translation-manager.source_locale', 'en');

        if (! is_string($sourceLocale) || $sourceLocale === '' || $sourceLocale === $write->locale) {
            return;
        }

        $sourceValues = TranslationArray::flattenStrings($this->read($write->source, $write->fileKey, $sourceLocale, false));

        foreach ($write->values as $key => $targetValue) {
            if (! is_string($targetValue)) {
                continue;
            }

            if ($targetValue === '') {
                continue;
            }

            $sourceValue = $sourceValues[$key] ?? null;
            if (! is_string($sourceValue)) {
                continue;
            }

            if ($sourceValue === '') {
                continue;
            }

            $this->assertPlaceholdersPreserved($key, $sourceValue, $targetValue);
            $this->assertPluralFormsPreserved($key, $sourceValue, $targetValue);
            $this->assertGlossaryTermsPreserved($key, $write->locale, $sourceValue, $targetValue);
        }
    }

    private function assertPlaceholdersPreserved(string $key, string $sourceValue, string $targetValue): void
    {
        $missingPlaceholders = array_values(array_diff(
            $this->placeholders($sourceValue),
            $this->placeholders($targetValue),
        ));

        throw_if($missingPlaceholders !== [], InvalidArgumentException::class, __(
            'capell-translation-manager::package.validation_missing_placeholders',
            [
                'key' => $key,
                'placeholders' => implode(', ', $missingPlaceholders),
            ],
        ));
    }

    private function assertPluralFormsPreserved(string $key, string $sourceValue, string $targetValue): void
    {
        $sourcePluralCount = substr_count($sourceValue, '|');

        if ($sourcePluralCount === 0) {
            return;
        }

        throw_unless(substr_count($targetValue, '|') === $sourcePluralCount, InvalidArgumentException::class, __(
            'capell-translation-manager::package.validation_plural_forms',
            ['key' => $key],
        ));
    }

    private function assertGlossaryTermsPreserved(string $key, string $locale, string $sourceValue, string $targetValue): void
    {
        foreach ($this->glossaryTerms($locale) as $sourceTerm => $targetTerm) {
            if (! str_contains(mb_strtolower($sourceValue), mb_strtolower($sourceTerm))) {
                continue;
            }

            throw_unless(str_contains(mb_strtolower($targetValue), mb_strtolower($targetTerm)), InvalidArgumentException::class, __(
                'capell-translation-manager::package.validation_glossary_term',
                [
                    'key' => $key,
                    'source' => $sourceTerm,
                    'target' => $targetTerm,
                ],
            ));
        }
    }

    /**
     * @return array<string, string>
     */
    private function glossaryTerms(string $locale): array
    {
        $terms = config('capell-translation-manager.glossary.' . $locale, []);

        if (! is_array($terms)) {
            return [];
        }

        return collect($terms)
            ->filter(static fn (mixed $targetTerm, mixed $sourceTerm): bool => is_string($sourceTerm) && $sourceTerm !== '' && is_string($targetTerm) && $targetTerm !== '')
            ->all();
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $value): array
    {
        preg_match_all('/(?<!:):[A-Za-z_]\w*/', $value, $matches);

        return array_values(array_unique($matches[0]));
    }

    /**
     * @return array<string, mixed>
     */
    private function read(TranslationSourceData $source, string $fileKey, string $locale, bool $forWrite): array
    {
        if (! $forWrite) {
            return $this->readMerged($source, $fileKey, $locale);
        }

        $path = $this->path($source, $fileKey, $locale, $forWrite);

        if ($fileKey === 'json') {
            if (! $this->filesystem->exists($path)) {
                return [];
            }

            try {
                $decoded = json_decode($this->filesystem->get($path), true, flags: JSON_THROW_ON_ERROR);

                return $this->validatedTranslationMap($decoded, 'JSON');
            } catch (Throwable $exception) {
                throw TranslationFileWriteException::readingFailed($path, $exception);
            }
        }

        if (! $this->filesystem->exists($path)) {
            return [];
        }

        try {
            $values = require $path;

            return $this->validatedTranslationMap($values, 'PHP');
        } catch (Throwable $exception) {
            throw TranslationFileWriteException::readingFailed($path, $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTranslationMap(mixed $values, string $format): array
    {
        if (! is_array($values)) {
            throw new RuntimeException(sprintf('%s translation files must contain a map.', $format));
        }

        $validated = [];

        foreach ($values as $key => $value) {
            if (! is_string($key)) {
                throw new RuntimeException(sprintf('%s translation file keys must be strings.', $format));
            }

            $validated[$key] = $value;
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function readMerged(TranslationSourceData $source, string $fileKey, string $locale): array
    {
        $sourcePath = $this->rawPath($source->sourcePath, $fileKey, $locale);
        $overridePath = $this->rawPath($source->overridePath, $fileKey, $locale);
        $sourceValues = $this->readPath($sourcePath, $fileKey);

        if ($overridePath === $sourcePath) {
            return $sourceValues;
        }

        return array_replace_recursive($sourceValues, $this->readPath($overridePath, $fileKey));
    }

    /**
     * @return array<string, mixed>
     */
    private function readPath(string $path, string $fileKey): array
    {
        if (! $this->filesystem->exists($path)) {
            return [];
        }

        if ($fileKey === 'json') {
            $decoded = json_decode($this->filesystem->get($path), true);

            return is_array($decoded) ? $decoded : [];
        }

        $values = require $path;

        return is_array($values) ? $values : [];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{path: string, contents: string, values: array<string, mixed>, type: 'json'|'php'}
     */
    private function translationArtifact(TranslationSourceData $source, string $fileKey, string $locale, array $values): array
    {
        $path = $this->path($source, $fileKey, $locale, true);

        if ($fileKey === 'json') {
            try {
                $encoded = json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw TranslationFileWriteException::encodingFailed($path, $exception);
            }

            return [
                'path' => $path,
                'contents' => $encoded . PHP_EOL,
                'values' => $values,
                'type' => 'json',
            ];
        }

        return [
            'path' => $path,
            'contents' => $this->exportPhpArray($values),
            'values' => $values,
            'type' => 'php',
        ];
    }

    /**
     * @param  non-empty-list<array{path: string, contents: string, values: array<string, mixed>, type: 'json'|'php'}>  $artifacts
     */
    private function publishArtifactsAtomically(array $artifacts): void
    {
        /** @var array<string, string> $stagedPaths */
        $stagedPaths = [];
        /** @var array<string, array{exists: bool, contents: string|null}> $originals */
        $originals = [];
        $translationPath = $artifacts[0]['path'];
        $journalPath = $this->publicationJournalPath($translationPath);
        $journalPublished = false;

        try {
            foreach ($artifacts as $artifact) {
                $path = $artifact['path'];
                $this->filesystem->ensureDirectoryExists(dirname($path));
                $originals[$path] = $this->originalArtifact($path);
                $stagedPaths[$path] = $this->stageArtifact($artifact);
            }

            $this->publishPublicationJournal($journalPath, $originals, $stagedPaths);
            $journalPublished = true;

            foreach ($artifacts as $artifact) {
                $path = $artifact['path'];
                $this->publishStagedFile($stagedPaths[$path], $path);
            }

            if (! $this->filesystem->delete($journalPath)) {
                throw TranslationFileWriteException::publicationFailed($journalPath);
            }

            $journalPublished = false;
        } catch (Throwable $exception) {
            if ($journalPublished && $this->filesystem->exists($journalPath)) {
                try {
                    $this->recoverInterruptedPublication($translationPath);
                } catch (Throwable $rollbackException) {
                    throw TranslationFileWriteException::rollbackFailed($translationPath, $rollbackException);
                }
            }

            throw $exception;
        } finally {
            foreach ($stagedPaths as $temporaryPath) {
                if ($this->filesystem->exists($temporaryPath)) {
                    $this->filesystem->delete($temporaryPath);
                }
            }
        }
    }

    /**
     * @param  array{path: string, contents: string, values: array<string, mixed>, type: 'json'|'php'}  $artifact
     */
    private function stageArtifact(array $artifact): string
    {
        $temporaryPath = $this->stageContents($artifact['path'], $artifact['contents']);

        try {
            $this->validateStagedFile(
                $temporaryPath,
                $artifact['contents'],
                $artifact['values'],
                $artifact['type'],
                $artifact['path'],
            );
        } catch (Throwable $exception) {
            $this->deleteTemporaryArtifact($temporaryPath);

            throw $exception;
        }

        return $temporaryPath;
    }

    private function stageContents(string $path, string $contents): string
    {
        $temporaryPath = $path . '.tmp.' . bin2hex(random_bytes(8));

        try {
            $bytesWritten = $this->filesystem->put($temporaryPath, $contents, true);
        } catch (Throwable $exception) {
            $this->deleteTemporaryArtifact($temporaryPath);

            throw TranslationFileWriteException::stagingFailed($path, $exception);
        }

        if ($bytesWritten !== strlen($contents)) {
            $this->deleteTemporaryArtifact($temporaryPath);

            throw TranslationFileWriteException::stagingFailed($path);
        }

        try {
            if ($this->filesystem->get($temporaryPath) !== $contents) {
                throw TranslationFileWriteException::validationFailed($path);
            }
        } catch (TranslationFileWriteException $exception) {
            $this->deleteTemporaryArtifact($temporaryPath);

            throw $exception;
        } catch (Throwable $exception) {
            $this->deleteTemporaryArtifact($temporaryPath);

            throw TranslationFileWriteException::validationFailed($path, $exception);
        }

        return $temporaryPath;
    }

    /**
     * @param  array<string, mixed>  $expectedValues
     * @param  'json'|'php'  $type
     */
    private function validateStagedFile(
        string $temporaryPath,
        string $contents,
        array $expectedValues,
        string $type,
        string $path,
    ): void {
        try {
            if ($this->filesystem->get($temporaryPath) !== $contents) {
                throw TranslationFileWriteException::validationFailed($path);
            }

            $stagedValues = $type === 'json'
                ? json_decode($contents, true, flags: JSON_THROW_ON_ERROR)
                : (static fn (string $file): mixed => require $file)($temporaryPath);

            if ($stagedValues !== $expectedValues) {
                throw TranslationFileWriteException::validationFailed($path);
            }
        } catch (TranslationFileWriteException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw TranslationFileWriteException::validationFailed($path, $exception);
        }
    }

    /** @return array{path: string, contents: string, values: array<string, mixed>, type: 'json'}|null */
    private function sourceHashMetadataArtifact(TranslationWriteData $write): ?array
    {
        $sourceLocale = config('capell-translation-manager.source_locale', 'en');

        if (! is_string($sourceLocale) || $sourceLocale === '' || $sourceLocale === $write->locale) {
            return null;
        }

        $sourceValues = TranslationArray::flattenStrings($this->read($write->source, $write->fileKey, $sourceLocale, false));
        $metadata = $this->readSourceHashMetadata($write->source, $write->fileKey, $write->locale);

        foreach ($write->values as $key => $targetValue) {
            $sourceValue = $sourceValues[$key] ?? null;

            if (! is_string($sourceValue) || ! is_string($targetValue) || $targetValue === '') {
                unset($metadata[$key]);

                continue;
            }

            $metadata[$key] = $this->sourceHash($sourceValue);
        }

        $metadataPath = $this->metadataPath($write->source, $write->fileKey, $write->locale);

        try {
            $encoded = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw TranslationFileWriteException::encodingFailed($metadataPath, $exception);
        }

        return [
            'path' => $metadataPath,
            'contents' => $encoded . PHP_EOL,
            'values' => $metadata,
            'type' => 'json',
        ];
    }

    /** @return array{exists: bool, contents: string|null} */
    private function originalArtifact(string $path): array
    {
        if (! $this->filesystem->exists($path)) {
            return ['exists' => false, 'contents' => null];
        }

        return ['exists' => true, 'contents' => $this->filesystem->get($path)];
    }

    private function publishStagedFile(string $temporaryPath, string $path): void
    {
        try {
            $published = $this->filesystem->move($temporaryPath, $path);
        } catch (Throwable $exception) {
            throw TranslationFileWriteException::publicationFailed($path, $exception);
        }

        if (! $published) {
            throw TranslationFileWriteException::publicationFailed($path);
        }
    }

    /**
     * @param  array<string, array{exists: bool, contents: string|null}>  $originals
     * @param  array<string, string>  $stagedPaths
     */
    private function publishPublicationJournal(string $journalPath, array $originals, array $stagedPaths): void
    {
        $artifacts = [];

        foreach ($originals as $path => $original) {
            $artifacts[] = [
                'path' => $path,
                'staged_path' => $stagedPaths[$path],
                'existed' => $original['exists'],
                'contents' => base64_encode($original['contents'] ?? ''),
            ];
        }

        try {
            $contents = json_encode([
                'version' => 1,
                'artifacts' => $artifacts,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        } catch (JsonException $exception) {
            throw TranslationFileWriteException::encodingFailed($journalPath, $exception);
        }

        $this->publishContentsAtomically($journalPath, $contents);
    }

    private function recoverInterruptedPublication(string $translationPath): void
    {
        $journalPath = $this->publicationJournalPath($translationPath);

        if (! $this->filesystem->exists($journalPath)) {
            return;
        }

        try {
            $journal = json_decode($this->filesystem->get($journalPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw TranslationFileWriteException::rollbackFailed($translationPath, $exception);
        }

        if (! is_array($journal) || ($journal['version'] ?? null) !== 1 || ! is_array($journal['artifacts'] ?? null)) {
            throw TranslationFileWriteException::rollbackFailed(
                $translationPath,
                new RuntimeException('Translation publication journal is invalid.'),
            );
        }

        $allowedPaths = [$translationPath, $translationPath . '.capell-meta.json'];

        foreach (array_reverse($journal['artifacts']) as $artifact) {
            if (! is_array($artifact)) {
                throw TranslationFileWriteException::rollbackFailed(
                    $translationPath,
                    new RuntimeException('Translation publication journal artifact is invalid.'),
                );
            }

            $path = $artifact['path'] ?? null;
            $stagedPath = $artifact['staged_path'] ?? null;
            $existed = $artifact['existed'] ?? null;
            $encodedContents = $artifact['contents'] ?? null;

            if (! is_string($path)
                || ! in_array($path, $allowedPaths, true)
                || ! is_string($stagedPath)
                || dirname($stagedPath) !== dirname($path)
                || ! str_starts_with(basename($stagedPath), basename($path) . '.tmp.')
                || ! is_bool($existed)
                || ! is_string($encodedContents)) {
                throw TranslationFileWriteException::rollbackFailed(
                    $translationPath,
                    new RuntimeException('Translation publication journal artifact is unsafe.'),
                );
            }

            $contents = base64_decode($encodedContents, true);

            if (! is_string($contents)) {
                throw TranslationFileWriteException::rollbackFailed(
                    $translationPath,
                    new RuntimeException('Translation publication journal contents are invalid.'),
                );
            }

            if (! $existed) {
                if ($this->filesystem->exists($path) && ! $this->filesystem->delete($path)) {
                    throw TranslationFileWriteException::publicationFailed($path);
                }
            } else {
                $this->publishContentsAtomically($path, $contents);
            }

            if ($this->filesystem->exists($stagedPath) && ! $this->filesystem->delete($stagedPath)) {
                throw TranslationFileWriteException::publicationFailed($stagedPath);
            }
        }

        if (! $this->filesystem->delete($journalPath)) {
            throw TranslationFileWriteException::publicationFailed($journalPath);
        }
    }

    private function publishContentsAtomically(string $path, string $contents): void
    {
        $temporaryPath = $this->stageContents($path, $contents);

        try {
            $this->publishStagedFile($temporaryPath, $path);
        } finally {
            $this->deleteTemporaryArtifact($temporaryPath);
        }
    }

    private function deleteTemporaryArtifact(string $path): void
    {
        if ($this->filesystem->exists($path)) {
            $this->filesystem->delete($path);
        }
    }

    private function publicationJournalPath(string $translationPath): string
    {
        return $translationPath . '.capell-transaction.json';
    }

    private function path(TranslationSourceData $source, string $fileKey, string $locale, bool $forWrite): string
    {
        $this->localeValidator->assertValid($locale);

        if ($fileKey === 'json') {
            return $this->basePath($source, $fileKey, $locale, $forWrite) . '/' . $locale . '.json';
        }

        $name = $this->phpFileName($fileKey);

        return $this->basePath($source, $fileKey, $locale, $forWrite) . '/' . $locale . '/' . $name . '.php';
    }

    private function metadataPath(TranslationSourceData $source, string $fileKey, string $locale): string
    {
        return $this->path($source, $fileKey, $locale, true) . '.capell-meta.json';
    }

    private function basePath(TranslationSourceData $source, string $fileKey, string $locale, bool $forWrite): string
    {
        if (! $forWrite) {
            $overridePath = $this->rawPath($source->overridePath, $fileKey, $locale);

            return $this->filesystem->exists($overridePath) ? $source->overridePath : $source->sourcePath;
        }

        if ($source->type === 'app') {
            return $source->sourcePath;
        }

        $overridePath = $this->rawPath($source->overridePath, $fileKey, $locale);

        if ($this->filesystem->exists($overridePath) || ! $source->sourceWritable) {
            return $source->overridePath;
        }

        return $source->sourcePath;
    }

    private function rawPath(string $basePath, string $fileKey, string $locale): string
    {
        if ($fileKey === 'json') {
            return $basePath . '/' . $locale . '.json';
        }

        return $basePath . '/' . $locale . '/' . $this->phpFileName($fileKey) . '.php';
    }

    private function existingPath(TranslationSourceData $source, string $fileKey, string $locale): ?string
    {
        foreach ([$source->overridePath, $source->sourcePath] as $basePath) {
            $path = $this->rawPath($basePath, $fileKey, $locale);

            if ($this->filesystem->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function phpFileName(string $fileKey): string
    {
        if (! str_starts_with($fileKey, 'php:')) {
            throw new InvalidArgumentException(sprintf('Translation file key [%s] is not allowed.', $fileKey));
        }

        $name = substr($fileKey, 4);

        if ($name === '' || str_starts_with($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
            throw new InvalidArgumentException(sprintf('Translation file key [%s] is not allowed.', $fileKey));
        }

        foreach (explode('/', $name) as $segment) {
            if (in_array($segment, ['', '.', '..'], true)) {
                throw new InvalidArgumentException(sprintf('Translation file key [%s] is not allowed.', $fileKey));
            }
        }

        return $name;
    }

    private function status(
        bool $sourceExists,
        bool $targetExists,
        bool $fallbackExists,
        ?string $sourceValue,
        ?string $targetValue,
        ?string $fallbackValue,
        ?int $sourceModifiedAt,
        ?int $targetModifiedAt,
        ?string $sourceHash = null,
        ?string $translatedSourceHash = null,
    ): string {
        if (! $sourceExists && $targetExists) {
            return 'extra';
        }

        if (! $targetExists || $targetValue === null || $targetValue === '') {
            if ($fallbackExists && $fallbackValue !== null && $fallbackValue !== '') {
                return 'fallback';
            }

            return 'missing';
        }

        if ($sourceValue === $targetValue) {
            return 'same';
        }

        if ($sourceHash !== null && $translatedSourceHash !== null) {
            return $sourceHash === $translatedSourceHash ? 'changed' : 'stale';
        }

        if ($sourceModifiedAt !== null && $targetModifiedAt !== null && $sourceModifiedAt > $targetModifiedAt) {
            return 'stale';
        }

        return 'changed';
    }

    private function fileModifiedAt(TranslationSourceData $source, string $fileKey, string $locale): ?int
    {
        $path = $this->existingPath($source, $fileKey, $locale);

        if ($path === null) {
            return null;
        }

        $modifiedAt = $this->filesystem->lastModified($path);

        return is_int($modifiedAt) ? $modifiedAt : null;
    }

    private function fallbackLocale(string $sourceLocale, string $targetLocale): ?string
    {
        $fallbackLocale = config('app.fallback_locale');

        if (! is_string($fallbackLocale) || $fallbackLocale === '') {
            return null;
        }

        if (in_array($fallbackLocale, [$sourceLocale, $targetLocale], true)) {
            return null;
        }

        return $this->localeValidator->isValid($fallbackLocale) ? $fallbackLocale : null;
    }

    /**
     * @return array<string, string>
     */
    private function readSourceHashMetadata(TranslationSourceData $source, string $fileKey, string $locale): array
    {
        $path = $this->metadataPath($source, $fileKey, $locale);

        if (! $this->filesystem->exists($path)) {
            return [];
        }

        $decoded = json_decode($this->filesystem->get($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(static fn (mixed $hash, mixed $key): bool => is_string($key) && is_string($hash))
            ->all();
    }

    private function sourceHash(string $sourceValue): string
    {
        return hash('sha256', $sourceValue);
    }

    private function sourceCacheKey(TranslationSourceData $source): string
    {
        return hash('sha256', implode("\0", [
            $source->key,
            $source->sourcePath,
            $source->overridePath,
            $source->namespace ?? '',
            $source->type,
            $source->sourceWritable ? '1' : '0',
        ]));
    }

    private function filesCacheKey(TranslationSourceData $source, string $sourceLocale, string $targetLocale): string
    {
        return implode(':', [
            $this->sourceCacheKey($source),
            $sourceLocale,
            $targetLocale,
        ]);
    }

    private function comparisonCacheKey(TranslationSourceData $source, string $fileKey, string $sourceLocale, string $targetLocale): string
    {
        $fallbackLocale = config('app.fallback_locale');

        return implode(':', [
            $this->sourceCacheKey($source),
            $fileKey,
            $sourceLocale,
            $targetLocale,
            is_string($fallbackLocale) ? $fallbackLocale : '',
        ]);
    }

    private function flushSourceCache(TranslationSourceData $source): void
    {
        $sourceCacheKey = $this->sourceCacheKey($source);

        unset($this->localesCache[$sourceCacheKey]);

        foreach (array_keys($this->filesCache) as $cacheKey) {
            if (str_starts_with($cacheKey, $sourceCacheKey . ':')) {
                unset($this->filesCache[$cacheKey]);
            }
        }

        foreach (array_keys($this->comparisonCache) as $cacheKey) {
            if (str_starts_with($cacheKey, $sourceCacheKey . ':')) {
                unset($this->comparisonCache[$cacheKey]);
            }
        }
    }

    /**
     * @param  resource  $stream
     * @return array<int, string>
     */
    private function readCsvHeaders(mixed $stream): array
    {
        $headers = fgetcsv($stream, escape: '\\');

        throw_if($headers === false, InvalidArgumentException::class, 'Translation CSV must contain a header row.');

        return array_map(
            static fn (mixed $header): string => ltrim(is_string($header) ? $header : '', "\u{FEFF}"),
            $headers,
        );
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function csvCell(array $row, int $index): string
    {
        $value = $row[$index] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function exportPhpArray(array $values): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nreturn " . $this->exportValue($values) . ";\n";
    }

    private function exportValue(mixed $value, int $depth = 0): string
    {
        if (! is_array($value)) {
            return var_export($value, true);
        }

        if ($value === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth);
        $childIndent = str_repeat('    ', $depth + 1);
        $lines = ['['];

        foreach ($value as $key => $childValue) {
            $lines[] = sprintf(
                '%s%s => %s,',
                $childIndent,
                var_export($key, true),
                $this->exportValue($childValue, $depth + 1),
            );
        }

        $lines[] = $indent . ']';

        return implode(PHP_EOL, $lines);
    }
}
