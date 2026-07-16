<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Data\TranslationCsvImportResultData;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class ImportTranslationEntriesFromPoAction
{
    use AsFake;
    use AsObject;

    public function handle(string $sourceKey, string $fileKey, string $locale, string $contents): TranslationCsvImportResultData
    {
        $values = [];
        $skippedCount = 0;

        foreach ($this->entries($contents) as $entry) {
            $key = $entry['msgctxt'] ?? null;
            $value = $entry['msgstr'] ?? null;

            if (! is_string($key) || $key === '' || ! is_string($value)) {
                $skippedCount++;

                continue;
            }

            $values[$key] = $value;
        }

        throw_if($values === [] && $skippedCount === 0, InvalidArgumentException::class, 'Translation PO did not contain any entries.');

        SaveTranslationEntriesAction::run($sourceKey, $fileKey, $locale, $values);

        return new TranslationCsvImportResultData(
            importedCount: count($values),
            skippedCount: $skippedCount,
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
