<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Data;

use Spatie\LaravelData\Data;

final class LocalePublishReadinessData extends Data
{
    /**
     * @param  array<string, int>  $statusCounts
     */
    public function __construct(
        public readonly string $sourceKey,
        public readonly string $sourceLocale,
        public readonly string $targetLocale,
        public readonly int $fileCount,
        public readonly int $entryCount,
        public readonly array $statusCounts,
        public readonly bool $ready,
    ) {}
}
