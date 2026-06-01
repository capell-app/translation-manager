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
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;

final class FileTranslationFileStore implements TranslationFileStore
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly LocaleValidator $localeValidator,
    ) {}

    public function locales(TranslationSourceData $source): array
    {
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

        return collect($locales)
            ->map(fn (array $locale): LocaleSummaryData => new LocaleSummaryData(
                locale: $locale['locale'],
                fileCount: count($this->files($source, $locale['locale'], $locale['locale'])),
                sourceAvailable: $locale['source'],
                overrideAvailable: $locale['override'],
            ))
            ->sortBy(fn (LocaleSummaryData $locale): string => $locale->locale)
            ->values()
            ->all();
    }

    public function files(TranslationSourceData $source, string $sourceLocale, string $targetLocale): array
    {
        $this->localeValidator->assertValid($sourceLocale);
        $this->localeValidator->assertValid($targetLocale);

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

        return collect($files)
            ->sortBy(fn (TranslationFileData $file): string => $file->label)
            ->values()
            ->all();
    }

    public function comparison(TranslationSourceData $source, string $fileKey, string $sourceLocale, string $targetLocale): array
    {
        $this->localeValidator->assertValid($sourceLocale);
        $this->localeValidator->assertValid($targetLocale);

        $sourceEntries = TranslationArray::flattenForEditor($this->read($source, $fileKey, $sourceLocale, false));
        $targetEntries = TranslationArray::flattenForEditor($this->read($source, $fileKey, $targetLocale, false));
        $sourceModifiedAt = $this->fileModifiedAt($source, $fileKey, $sourceLocale);
        $targetModifiedAt = $this->fileModifiedAt($source, $fileKey, $targetLocale);
        $keys = collect([...array_keys($sourceEntries), ...array_keys($targetEntries)])->unique()->sort()->values();

        return $keys
            ->map(function (string $key) use ($sourceEntries, $targetEntries, $sourceModifiedAt, $targetModifiedAt): TranslationEntryData {
                $sourceEntry = $sourceEntries[$key] ?? ['value' => null, 'editable' => false, 'exists' => false];
                $targetEntry = $targetEntries[$key] ?? [
                    'value' => null,
                    'editable' => $sourceEntry['editable'],
                    'exists' => false,
                ];
                $sourceValue = $sourceEntry['value'];
                $targetValue = $targetEntry['value'];
                $sourceExists = $sourceEntry['exists'];
                $targetExists = $targetEntry['exists'];

                return new TranslationEntryData(
                    key: $key,
                    sourceValue: is_string($sourceValue) ? $sourceValue : null,
                    targetValue: is_string($targetValue) ? $targetValue : null,
                    status: $this->status(
                        sourceExists: $sourceExists,
                        targetExists: $targetExists,
                        sourceValue: is_string($sourceValue) ? $sourceValue : null,
                        targetValue: is_string($targetValue) ? $targetValue : null,
                        sourceModifiedAt: $sourceModifiedAt,
                        targetModifiedAt: $targetModifiedAt,
                    ),
                    editable: $sourceEntry['editable'] || $targetEntry['editable'],
                );
            })
            ->all();
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

        $currentValues = $this->read($write->source, $write->fileKey, $write->locale, true);

        foreach ($write->values as $key => $value) {
            if ($write->fileKey === 'json') {
                $currentValues[$key] = $value ?? '';

                continue;
            }

            $currentValues = TranslationArray::setNestedValue($currentValues, $key, $value ?? '');
        }

        $this->writeValues($write->source, $write->fileKey, $write->locale, $currentValues);
    }

    public function exportCsv(TranslationSourceData $source, string $fileKey, string $sourceLocale, string $targetLocale): string
    {
        $stream = fopen('php://temp', 'r+');

        throw_if($stream === false, InvalidArgumentException::class, 'Unable to open temporary translation CSV stream.');

        try {
            fputcsv($stream, ['key', 'source_value', 'target_value', 'status']);

            foreach ($this->comparison($source, $fileKey, $sourceLocale, $targetLocale) as $entry) {
                fputcsv($stream, [
                    $entry->key,
                    $entry->sourceValue ?? '',
                    $entry->targetValue ?? '',
                    $entry->status,
                ]);
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

            while (($row = fgetcsv($stream)) !== false) {
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
     * @return array<string, mixed>
     */
    private function read(TranslationSourceData $source, string $fileKey, string $locale, bool $forWrite): array
    {
        $path = $this->path($source, $fileKey, $locale, $forWrite);

        if ($fileKey === 'json') {
            if (! $this->filesystem->exists($path)) {
                return [];
            }

            $decoded = json_decode($this->filesystem->get($path), true);

            return is_array($decoded) ? $decoded : [];
        }

        if (! $this->filesystem->exists($path)) {
            return [];
        }

        $values = require $path;

        return is_array($values) ? $values : [];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function writeValues(TranslationSourceData $source, string $fileKey, string $locale, array $values): void
    {
        $path = $this->path($source, $fileKey, $locale, true);
        $this->filesystem->ensureDirectoryExists(dirname($path));

        if ($fileKey === 'json') {
            $encoded = json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->filesystem->put($path, ($encoded === false ? '{}' : $encoded) . PHP_EOL);

            return;
        }

        $this->filesystem->put($path, $this->exportPhpArray($values));
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
        ?string $sourceValue,
        ?string $targetValue,
        ?int $sourceModifiedAt,
        ?int $targetModifiedAt,
    ): string {
        if (! $sourceExists && $targetExists) {
            return 'extra';
        }

        if (! $targetExists || $targetValue === null || $targetValue === '') {
            return 'missing';
        }

        if ($sourceValue === $targetValue) {
            return 'same';
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

    /**
     * @param  resource  $stream
     * @return array<int, string>
     */
    private function readCsvHeaders(mixed $stream): array
    {
        $headers = fgetcsv($stream);

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
