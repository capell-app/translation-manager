<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Data\AITranslationSuggestionData;
use Capell\TranslationManager\Data\TranslationEntryData;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildTranslationMemorySuggestionsAction
{
    use AsFake;
    use AsObject;

    private const string VERSION_KEY = 'capell-translation-manager:memory-version';

    /**
     * Every translation write moves the version on, so remembered suggestions never outlive an edit.
     */
    public static function forgetMemory(): void
    {
        Cache::forever(self::VERSION_KEY, self::memoryVersion() + 1);
    }

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

    private static function memoryVersion(): int
    {
        $version = Cache::get(self::VERSION_KEY, 0);

        return is_int($version) ? $version : 0;
    }

    private static function memoryCacheKey(string $sourceKey, string $sourceLocale, string $targetLocale): string
    {
        return implode(':', ['capell-translation-manager:memory', self::memoryVersion(), $sourceKey, $sourceLocale, $targetLocale]);
    }

    /**
     * @return array<string, string>
     */
    private function memory(string $sourceKey, string $sourceLocale, string $targetLocale): array
    {
        /** @var array<string, string> */
        return Cache::remember(
            self::memoryCacheKey($sourceKey, $sourceLocale, $targetLocale),
            now()->addMinutes(10),
            fn (): array => $this->buildMemory($sourceKey, $sourceLocale, $targetLocale),
        );
    }

    /**
     * @return array<string, string>
     */
    private function buildMemory(string $sourceKey, string $sourceLocale, string $targetLocale): array
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
