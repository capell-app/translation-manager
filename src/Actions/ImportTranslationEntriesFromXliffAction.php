<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Contracts\TranslationFileStore;
use Capell\TranslationManager\Contracts\TranslationSourceResolver;
use Capell\TranslationManager\Data\TranslationCsvImportResultData;
use Capell\TranslationManager\Exceptions\TranslationImportFormatException;
use Capell\TranslationManager\Support\TranslationImportFilter;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static TranslationCsvImportResultData run(string $sourceKey, string $fileKey, string $locale, string $contents, bool $dryRun = false)
 */
final class ImportTranslationEntriesFromXliffAction
{
    use AsFake;
    use AsObject;

    /**
     * Reads XLIFF 1.2 (trans-unit) and 2.0 (unit/segment) documents. Inline markup is reduced
     * to the text a translator sees: placeholders keep their equivalent text, wrapping tags
     * keep their content.
     */
    public function handle(string $sourceKey, string $fileKey, string $locale, string $contents, bool $dryRun = false): TranslationCsvImportResultData
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
        $units = $xpath->query('//*[local-name() = "trans-unit" or local-name() = "unit"]');

        throw_if($units === false, TranslationImportFormatException::class, 'xliff_xml');

        $rows = [];
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

            $key = $unit->getAttribute('resname') ?: ($unit->getAttribute('name') ?: $unit->getAttribute('id'));
            $target = $xpath->query('*[local-name() = "target"] | *[local-name() = "segment"]/*[local-name() = "target"]', $unit)?->item(0);

            if ($key === '' || ! $target instanceof DOMElement) {
                $skippedCount++;
                if (count($diagnostics) < 10) {
                    $diagnostics[] = ['position' => $position, 'reason' => $key === '' ? 'missing_key' : 'missing_target'];
                }

                continue;
            }

            $rows[] = ['position' => $position, 'key' => $key, 'value' => $this->inlineText($target)];
        }

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

    private function inlineText(DOMNode $node): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $equivalent = $child->getAttribute('equiv') ?: ($child->getAttribute('equiv-text') ?: $child->getAttribute('disp'));

                $text .= $equivalent !== '' && ! $child->hasChildNodes() ? $equivalent : $this->inlineText($child);

                continue;
            }

            $text .= $child->textContent;
        }

        return $text;
    }
}
