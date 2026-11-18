<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Contracts\TranslationFileStore;
use Capell\TranslationManager\Contracts\TranslationSourceResolver;
use Capell\TranslationManager\Data\TranslationCsvImportResultData;
use Capell\TranslationManager\Exceptions\TranslationImportFormatException;
use Capell\TranslationManager\Support\TranslationImportFilter;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static TranslationCsvImportResultData run(string $sourceKey, string $fileKey, string $locale, string $contents, bool $dryRun = false)
 */
final class ImportTranslationEntriesFromPoAction
{
    use AsFake;
    use AsObject;

    public function handle(string $sourceKey, string $fileKey, string $locale, string $contents, bool $dryRun = false): TranslationCsvImportResultData
    {
        $rows = [];
        $skippedCount = 0;
        $diagnostics = [];
        $position = 0;

        foreach ($this->entries($contents) as $entry) {
            $position++;
            $key = $entry['msgctxt'] ?? null;
            $value = $entry['msgstr'] ?? null;

            if (! is_string($key) || $key === '' || ! is_string($value)) {
                $skippedCount++;
                if (count($diagnostics) < 10) {
                    $diagnostics[] = ['position' => $position, 'reason' => ! is_string($key) || $key === '' ? 'missing_context' : 'missing_target'];
                }

                continue;
            }

            $rows[] = ['position' => $position, 'key' => $key, 'value' => $value];
        }

        throw_if($rows === [] && $skippedCount === 0, TranslationImportFormatException::class, 'po_empty');

        $source = resolve(TranslationSourceResolver::class)->source($sourceKey);
        $store = resolve(TranslationFileStore::class);
        $selection = TranslationImportFilter::filter(
            $rows,
            $sourceKey,
            $fileKey,
            $locale,
            $skippedCount,
            $diagnostics,
            static fn (string $key, string $value): bool => $store->valueIsValid($source, $fileKey, $locale, $key, $value),
        );

        if (! $dryRun && $selection['values'] !== []) {
            SaveTranslationEntriesAction::run($sourceKey, $fileKey, $locale, $selection['values']);
        }

        return new TranslationCsvImportResultData(
            importedCount: count($selection['values']),
            skippedCount: $selection['skipped'],
            diagnostics: $selection['diagnostics'],
            dryRun: $dryRun,
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function entries(string $contents): array
    {
        $entries = [];
        $current = [];
        $currentField = null;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                if ($current !== []) {
                    $entries[] = $current;
                }

                $current = [];
                $currentField = null;

                continue;
            }

            if (str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^(msgctxt|msgid|msgstr)\s+"(.*)"$/', $line, $matches) === 1) {
                $currentField = $matches[1];
                $current[$currentField] = $this->unescape($matches[2]);

                continue;
            }

            if ($currentField !== null && preg_match('/^"(.*)"$/', $line, $matches) === 1) {
                $current[$currentField] = ($current[$currentField] ?? '') . $this->unescape($matches[1]);
            }
        }

        if ($current !== []) {
            $entries[] = $current;
        }

        return $entries;
    }

    private function unescape(string $value): string
    {
        return stripcslashes($value);
    }
}
