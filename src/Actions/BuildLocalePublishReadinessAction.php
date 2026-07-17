<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Data\LocalePublishReadinessData;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static LocalePublishReadinessData run(string $sourceKey, string $sourceLocale, string $targetLocale)
 */
final class BuildLocalePublishReadinessAction
{
    use AsFake;
    use AsObject;

    public function handle(string $sourceKey, string $sourceLocale, string $targetLocale): LocalePublishReadinessData
    {
        $files = ListTranslationFilesAction::run($sourceKey, $sourceLocale, $targetLocale);
        $statusCounts = [
            'missing' => 0,
            'stale' => 0,
            'changed' => 0,
            'same' => 0,
            'extra' => 0,
            'fallback' => 0,
        ];
        $entryCount = 0;

        foreach ($files as $file) {
            foreach (LoadTranslationComparisonAction::run($sourceKey, $file->key, $sourceLocale, $targetLocale) as $entry) {
                if (! $entry->editable) {
                    continue;
                }

                $entryCount++;
                $statusCounts[$entry->status] = ($statusCounts[$entry->status] ?? 0) + 1;
            }
        }

        return new LocalePublishReadinessData(
            sourceKey: $sourceKey,
            sourceLocale: $sourceLocale,
            targetLocale: $targetLocale,
            fileCount: count($files),
            entryCount: $entryCount,
            statusCounts: $statusCounts,
            ready: ($statusCounts['missing'] ?? 0) === 0 && ($statusCounts['stale'] ?? 0) === 0,
        );
    }
}
