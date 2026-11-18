<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Data;

use Spatie\LaravelData\Data;

final class TranslationCsvImportResultData extends Data
{
    /** @param list<array{position: int, reason: string}> $diagnostics */
    public function __construct(
        public readonly int $importedCount,
        public readonly int $skippedCount,
        public readonly array $diagnostics = [],
        public readonly bool $dryRun = false,
    ) {}
}
