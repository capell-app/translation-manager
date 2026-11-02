<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Data\TranslationCsvImportResultData;
use Capell\TranslationManager\Exceptions\TranslationImportFormatException;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static TranslationCsvImportResultData run(string $sourceKey, string $fileKey, string $locale, string $contents)
 */
final class ImportTranslationEntriesFromXliffAction
{
    use AsFake;
    use AsObject;

    public function handle(string $sourceKey, string $fileKey, string $locale, string $contents): TranslationCsvImportResultData
    {
        if ($contents === '') {
            throw new TranslationImportFormatException('xliff_empty');
        }

        throw_if(
            preg_match('/<!DOCTYPE(?:\\s|>)/i', $contents) === 1,
            TranslationImportFormatException::class,
            'xliff_doctype',
        );

        $document = new DOMDocument;
        $loaded = @$document->loadXML($contents, LIBXML_NONET);

        throw_if(! $loaded, TranslationImportFormatException::class, 'xliff_xml');

        $xpath = new DOMXPath($document);
        $units = $xpath->query('//*[local-name() = "trans-unit"]');

        throw_if($units === false, TranslationImportFormatException::class, 'xliff_xml');

        $values = [];
        $skippedCount = 0;
        $diagnostics = [];
        $position = 0;

        foreach ($units as $unit) {
            $position++;
            if (! $unit instanceof DOMElement) {
                $skippedCount++;
                if (count($diagnostics) < 10) {
                    $diagnostics[] = ['position' => $position, 'reason' => 'missing_key'];
                }

                continue;
            }

            $key = $unit->getAttribute('resname') ?: $unit->getAttribute('id');
            $target = $xpath->query('*[local-name() = "target"]', $unit)?->item(0);

            if ($key === '' || ! $target instanceof DOMElement) {
                $skippedCount++;
                if (count($diagnostics) < 10) {
                    $diagnostics[] = ['position' => $position, 'reason' => $key === '' ? 'missing_key' : 'missing_target'];
                }

                continue;
            }

            $values[$key] = $target->textContent;
        }

        SaveTranslationEntriesAction::run($sourceKey, $fileKey, $locale, $values);

        return new TranslationCsvImportResultData(
            importedCount: count($values),
            skippedCount: $skippedCount,
            diagnostics: $diagnostics,
        );
    }
}
