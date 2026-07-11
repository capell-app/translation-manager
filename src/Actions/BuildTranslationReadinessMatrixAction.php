<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Data\LocalePublishReadinessData;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static list<array{locale: string, fileCount: int, entryCount: int, missing: int, stale: int, changed: int, same: int, extra: int, fallback: int, ready: bool}> run(?string $sourceKey, string $sourceLocale, list<array{locale: string, fileCount: int, sourceAvailable: bool, overrideAvailable: bool}> $locales)
 */
final class BuildTranslationReadinessMatrixAction
{
    use AsObject;

    /**
     * @param  list<array{locale: string, fileCount: int, sourceAvailable: bool, overrideAvailable: bool}>  $locales
     * @return list<array{locale: string, fileCount: int, entryCount: int, missing: int, stale: int, changed: int, same: int, extra: int, fallback: int, ready: bool}>
     */
    public function handle(?string $sourceKey, string $sourceLocale, array $locales): array
    {
        if ($sourceKey === null || $locales === []) {
            return [];
        }

        return array_values(array_filter(array_map(
            function (array $locale) use ($sourceKey, $sourceLocale): ?array {
                if ($locale['locale'] === $sourceLocale) {
                    return null;
                }

                return $this->readinessRow(BuildLocalePublishReadinessAction::run(
                    $sourceKey,
                    $sourceLocale,
                    $locale['locale'],
                ));
            },
            $locales,
        )));
    }

    /**
     * @return array{locale: string, fileCount: int, entryCount: int, missing: int, stale: int, changed: int, same: int, extra: int, fallback: int, ready: bool}
     */
    private function readinessRow(LocalePublishReadinessData $readiness): array
    {
        return [
            'locale' => $readiness->targetLocale,
            'fileCount' => $readiness->fileCount,
            'entryCount' => $readiness->entryCount,
            'missing' => $readiness->statusCounts['missing'] ?? 0,
            'stale' => $readiness->statusCounts['stale'] ?? 0,
            'changed' => $readiness->statusCounts['changed'] ?? 0,
            'same' => $readiness->statusCounts['same'] ?? 0,
            'extra' => $readiness->statusCounts['extra'] ?? 0,
            'fallback' => $readiness->statusCounts['fallback'] ?? 0,
            'ready' => $readiness->ready,
        ];
    }
}
