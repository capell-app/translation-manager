<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Integrations\AI;

use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\TranslationManager\Actions\TranslateSelectedEntriesAction;
use Capell\TranslationManager\Data\TranslationEntryData;
use Lorisleiva\Actions\Concerns\AsObject;

final class DraftSelectedTranslationsAction
{
    use AsObject;

    /**
     * @return array<int, array{key: string, value: string}>
     */
    public function handle(AIOrchestratorRunData $run): array
    {
        $sourceLocale = is_string($run->context['source_locale'] ?? null) ? $run->context['source_locale'] : 'en';
        $targetLocale = is_string($run->context['target_locale'] ?? null) ? $run->context['target_locale'] : 'en';
        $selectedKeys = is_array($run->context['selected_keys'] ?? null) ? $run->context['selected_keys'] : [];
        $entries = is_array($run->context['entries'] ?? null) ? $run->context['entries'] : [];

        $entryData = collect($entries)
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->map(fn (array $entry): TranslationEntryData => new TranslationEntryData(
                key: (string) ($entry['key'] ?? ''),
                sourceValue: isset($entry['sourceValue']) && is_string($entry['sourceValue']) ? $entry['sourceValue'] : null,
                targetValue: isset($entry['targetValue']) && is_string($entry['targetValue']) ? $entry['targetValue'] : null,
                status: (string) ($entry['status'] ?? 'missing'),
                editable: (bool) ($entry['editable'] ?? false),
            ))
            ->filter(fn (TranslationEntryData $entry): bool => $entry->key !== '')
            ->values()
            ->all();

        return collect(TranslateSelectedEntriesAction::run($sourceLocale, $targetLocale, $entryData, array_values($selectedKeys)))
            ->map(fn ($suggestion): array => [
                'key' => $suggestion->key,
                'value' => $suggestion->value,
            ])
            ->all();
    }
}
