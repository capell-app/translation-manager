<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Data\AITranslationSuggestionData;
use Capell\TranslationManager\Data\TranslationEntryData;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildTranslationMemorySuggestionsAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<int, TranslationEntryData>  $entries
     * @return array<int, AITranslationSuggestionData>
     */
    public function handle(string $sourceKey, string $sourceLocale, string $targetLocale, array $entries): array
    {
        $memory = $this->memory($sourceKey, $sourceLocale, $targetLocale);

        return collect($entries)
            ->filter(static fn (TranslationEntryData $entry): bool => $entry->editable && $entry->sourceValue !== null && $entry->sourceValue !== '')
            ->map(fn (TranslationEntryData $entry): ?AITranslationSuggestionData => isset($memory[$entry->sourceValue])
                ? new AITranslationSuggestionData($entry->key, $memory[$entry->sourceValue])
                : null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function memory(string $sourceKey, string $sourceLocale, string $targetLocale): array
    {
        $memory = [];

        foreach (ListTranslationFilesAction::run($sourceKey, $sourceLocale, $targetLocale) as $file) {
            foreach (LoadTranslationComparisonAction::run($sourceKey, $file->key, $sourceLocale, $targetLocale) as $entry) {
                if ($entry->sourceValue === null) {
                    continue;
                }

                if ($entry->targetValue === null) {
                    continue;
                }

                if ($entry->targetValue === '') {
                    continue;
                }

                if ($entry->targetValue === $entry->sourceValue) {
                    continue;
                }

                $memory[$entry->sourceValue] ??= $entry->targetValue;
            }
        }

        return $memory;
    }
}
