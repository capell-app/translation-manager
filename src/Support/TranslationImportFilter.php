<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Support;

use Capell\TranslationManager\Actions\LoadTranslationComparisonAction;
use Closure;

/**
 * Decides which parsed import rows may be written. A row is skipped, and reported by its
 * position, when its key does not exist in the source language its value is blank or it fails the placeholder, plural and glossary checks, so an
 * import can neither invent keys nor erase an existing translation with an empty cell.
 */
final class TranslationImportFilter
{
    private const int DIAGNOSTIC_LIMIT = 10;

    /**
     * @param  list<array{position: int, key: string, value: string}>  $rows
     * @param  list<array{position: int, reason: string}>  $diagnostics  Diagnostics already collected by the parser.
     * @param  Closure(string, string): bool|null  $isValid
     * @return array{values: array<string, string>, skipped: int, diagnostics: list<array{position: int, reason: string}>}
     */
    public static function filter(array $rows, string $sourceKey, string $fileKey, string $locale, int $skipped = 0, array $diagnostics = [], ?Closure $isValid = null): array
    {
        $knownKeys = self::knownKeys($sourceKey, $fileKey, $locale);
        $values = [];

        foreach ($rows as $row) {
            $reason = match (true) {
                ! isset($knownKeys[$row['key']]) => 'unknown_key',
                $row['value'] === '' => 'blank_value',
                $isValid !== null && ! $isValid($row['key'], $row['value']) => 'invalid_value',
                default => null,
            };

            if ($reason === null) {
                $values[$row['key']] = $row['value'];

                continue;
            }

            $skipped++;
            $diagnostics[] = ['position' => $row['position'], 'reason' => $reason];
        }

        usort($diagnostics, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return [
            'values' => $values,
            'skipped' => $skipped,
            'diagnostics' => array_slice($diagnostics, 0, self::DIAGNOSTIC_LIMIT),
        ];
    }

    /**
     * @return array<string, true>
     */
    private static function knownKeys(string $sourceKey, string $fileKey, string $locale): array
    {
        $sourceLocale = config('capell-translation-manager.source_locale', 'en');
        $sourceLocale = is_string($sourceLocale) && $sourceLocale !== '' ? $sourceLocale : 'en';
        $known = [];

        foreach (LoadTranslationComparisonAction::run($sourceKey, $fileKey, $sourceLocale, $locale) as $entry) {
            if ($entry->sourceValue !== null) {
                $known[$entry->key] = true;
            }
        }

        return $known;
    }
}
