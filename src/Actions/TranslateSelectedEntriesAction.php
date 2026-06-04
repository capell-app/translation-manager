<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Contracts\TranslationAITranslator;
use Capell\TranslationManager\Data\AITranslationSuggestionData;
use Capell\TranslationManager\Data\TranslationEntryData;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static array<int, AITranslationSuggestionData> run(string $sourceLocale, string $targetLocale, array<int, TranslationEntryData> $entries, array<int, string> $selectedKeys, ?string $sourceKey = null)
 */
final class TranslateSelectedEntriesAction
{
    use AsObject;

    /**
     * @param  array<int, TranslationEntryData>  $entries
     * @param  array<int, string>  $selectedKeys
     * @return array<int, AITranslationSuggestionData>
     */
    public function handle(string $sourceLocale, string $targetLocale, array $entries, array $selectedKeys, ?string $sourceKey = null): array
    {
        $selected = collect($entries)
            ->filter(fn (TranslationEntryData $entry): bool => in_array($entry->key, $selectedKeys, true))
            ->filter(fn (TranslationEntryData $entry): bool => $entry->editable && $entry->sourceValue !== null && $entry->sourceValue !== '')
            ->values()
            ->all();

        $memorySuggestions = $sourceKey === null
            ? []
            : BuildTranslationMemorySuggestionsAction::run($sourceKey, $sourceLocale, $targetLocale, $selected);
        $memoryKeys = array_map(
            static fn (AITranslationSuggestionData $suggestion): string => $suggestion->key,
            $memorySuggestions,
        );

        $translator = resolve(TranslationAITranslator::class);

        if (! $translator->available()) {
            return $memorySuggestions;
        }

        $remaining = collect($selected)
            ->reject(fn (TranslationEntryData $entry): bool => in_array($entry->key, $memoryKeys, true))
            ->values()
            ->all();

        return [
            ...$memorySuggestions,
            ...$translator->translateSelected($sourceLocale, $targetLocale, $remaining),
        ];
    }
}
