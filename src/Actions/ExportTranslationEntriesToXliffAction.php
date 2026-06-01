<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Data\TranslationEntryData;
use DOMDocument;
use DOMElement;
use Lorisleiva\Actions\Concerns\AsObject;

final class ExportTranslationEntriesToXliffAction
{
    use AsObject;

    public function handle(string $sourceKey, string $fileKey, string $sourceLocale, string $targetLocale): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $xliff = $document->createElement('xliff');
        $xliff->setAttribute('version', '1.2');
        $document->appendChild($xliff);

        $file = $document->createElement('file');
        $file->setAttribute('source-language', $sourceLocale);
        $file->setAttribute('target-language', $targetLocale);
        $file->setAttribute('datatype', 'plaintext');
        $file->setAttribute('original', $sourceKey . ':' . $fileKey);
        $xliff->appendChild($file);

        $body = $document->createElement('body');
        $file->appendChild($body);

        foreach (LoadTranslationComparisonAction::run($sourceKey, $fileKey, $sourceLocale, $targetLocale) as $entry) {
            if (! $entry instanceof TranslationEntryData) {
                continue;
            }

            $body->appendChild($this->translationUnit($document, $entry));
        }

        return $document->saveXML() ?: '';
    }

    private function translationUnit(DOMDocument $document, TranslationEntryData $entry): DOMElement
    {
        $unit = $document->createElement('trans-unit');
        $unit->setAttribute('id', $entry->key);
        $unit->setAttribute('resname', $entry->key);

        $source = $document->createElement('source');
        $source->appendChild($document->createTextNode($entry->sourceValue ?? ''));
        $unit->appendChild($source);

        $target = $document->createElement('target');
        $target->setAttribute('state', $entry->status);
        $target->appendChild($document->createTextNode($entry->targetValue ?? ''));
        $unit->appendChild($target);

        return $unit;
    }
}
