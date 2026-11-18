<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Data\TranslationEntryData;
use DOMDocument;
use DOMElement;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class ExportTranslationEntriesToXliffAction
{
    use AsFake;
    use AsObject;

    public function handle(string $sourceKey, string $fileKey, string $sourceLocale, string $targetLocale, string $version = '1.2'): string
    {
        if ($version === '2.0') {
            return $this->xliff2($sourceKey, $fileKey, $sourceLocale, $targetLocale);
        }

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
            $body->appendChild($this->translationUnit($document, $entry));
        }

        return $document->saveXML() ?: '';
    }

    private function xliff2(string $sourceKey, string $fileKey, string $sourceLocale, string $targetLocale): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $xliff = $document->createElementNS('urn:oasis:names:tc:xliff:document:2.0', 'xliff');
        $xliff->setAttribute('version', '2.0');
        $xliff->setAttribute('srcLang', $sourceLocale);
        $xliff->setAttribute('trgLang', $targetLocale);
        $document->appendChild($xliff);

        $file = $document->createElementNS('urn:oasis:names:tc:xliff:document:2.0', 'file');
        $file->setAttribute('id', 'f1');
        $file->setAttribute('original', $sourceKey . ':' . $fileKey);
        $xliff->appendChild($file);

        foreach (LoadTranslationComparisonAction::run($sourceKey, $fileKey, $sourceLocale, $targetLocale) as $entry) {
            $unit = $document->createElementNS('urn:oasis:names:tc:xliff:document:2.0', 'unit');
            $unit->setAttribute('id', $entry->key);
            $unit->setAttribute('name', $entry->key);

            $segment = $document->createElementNS('urn:oasis:names:tc:xliff:document:2.0', 'segment');
            $segment->setAttribute('state', $entry->targetValue === null || $entry->targetValue === '' ? 'initial' : 'translated');

            $source = $document->createElementNS('urn:oasis:names:tc:xliff:document:2.0', 'source');
            $source->appendChild($document->createTextNode($entry->sourceValue ?? ''));
            $segment->appendChild($source);

            $target = $document->createElementNS('urn:oasis:names:tc:xliff:document:2.0', 'target');
            $target->appendChild($document->createTextNode($entry->targetValue ?? ''));
            $segment->appendChild($target);

            $unit->appendChild($segment);
            $file->appendChild($unit);
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
