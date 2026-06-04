<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Data\TranslationEntryData;
use Lorisleiva\Actions\Concerns\AsObject;

final class ExportTranslationEntriesToPoAction
{
    use AsObject;

    public function handle(string $sourceKey, string $fileKey, string $sourceLocale, string $targetLocale): string
    {
        $lines = [
            'msgid ""',
            'msgstr ""',
            '"Content-Type: text/plain; charset=UTF-8\n"',
            '"Content-Transfer-Encoding: 8bit\n"',
            '',
        ];

        foreach (LoadTranslationComparisonAction::run($sourceKey, $fileKey, $sourceLocale, $targetLocale) as $entry) {
            if (! $entry instanceof TranslationEntryData) {
                continue;
            }

            $lines[] = '#. ' . $entry->status;
            $lines[] = 'msgctxt "' . $this->escape($entry->key) . '"';
            $lines[] = 'msgid "' . $this->escape($entry->sourceValue ?? '') . '"';
            $lines[] = 'msgstr "' . $this->escape($entry->targetValue ?? '') . '"';
            $lines[] = '';
        }

        return implode(PHP_EOL, $lines);
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', '"', "\n", "\r", "\t"],
            ['\\\\', '\"', '\n', '\r', '\t'],
            $value,
        );
    }
}
