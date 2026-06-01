<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Data\TranslationCsvImportResultData;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsObject;

final class ImportTranslationEntriesFromXliffAction
{
    use AsObject;

    public function handle(string $sourceKey, string $fileKey, string $locale, string $contents): TranslationCsvImportResultData
    {
        $document = new DOMDocument;
        $loaded = @$document->loadXML($contents);

        throw_if(! $loaded, InvalidArgumentException::class, 'Translation XLIFF must be valid XML.');

        $xpath = new DOMXPath($document);
        $units = $xpath->query('//*[local-name() = "trans-unit"]');

        throw_if($units === false, InvalidArgumentException::class, 'Translation XLIFF could not be parsed.');

        $values = [];
        $skippedCount = 0;

        foreach ($units as $unit) {
            if (! $unit instanceof DOMElement) {
                $skippedCount++;

                continue;
            }

            $key = $unit->getAttribute('resname') ?: $unit->getAttribute('id');
            $target = $xpath->query('*[local-name() = "target"]', $unit)?->item(0);

            if ($key === '' || ! $target instanceof DOMElement) {
                $skippedCount++;

                continue;
            }

            $values[$key] = $target->textContent;
        }

        SaveTranslationEntriesAction::run($sourceKey, $fileKey, $locale, $values);

        return new TranslationCsvImportResultData(
            importedCount: count($values),
            skippedCount: $skippedCount,
        );
    }
}
