<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Data\TranslationQueueItemData;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static ?TranslationQueueItemData run(string $sourceKey, string $sourceLocale, string $targetLocale)
 */
final class FindNextTranslationWorkAction
{
    use AsFake;
    use AsObject;

    public function handle(string $sourceKey, string $sourceLocale, string $targetLocale): ?TranslationQueueItemData
    {
        $candidates = [];

        foreach (ListTranslationFilesAction::run($sourceKey, $sourceLocale, $targetLocale) as $file) {
            foreach (LoadTranslationComparisonAction::run($sourceKey, $file->key, $sourceLocale, $targetLocale) as $entry) {
                if (! $entry->editable || ! in_array($entry->status, ['missing', 'stale', 'changed'], true)) {
                    continue;
                }

                $candidates[] = [
                    'fileKey' => $file->key,
                    'fileLabel' => $file->label,
                    'key' => $entry->key,
                    'status' => $entry->status,
                    'priority' => match ($entry->status) {
                        'missing' => 0,
                        'stale' => 1,
                        default => 2,
                    },
                ];
            }
        }

        usort($candidates, static fn (array $left, array $right): int => [
            $left['priority'],
            $left['fileKey'],
            $left['key'],
        ] <=> [
            $right['priority'],
            $right['fileKey'],
            $right['key'],
        ]);

        $candidate = $candidates[0] ?? null;

        if (! is_array($candidate)) {
            return null;
        }

        return new TranslationQueueItemData(
            fileKey: $candidate['fileKey'],
            fileLabel: $candidate['fileLabel'],
            key: $candidate['key'],
            status: $candidate['status'],
        );
    }
}
