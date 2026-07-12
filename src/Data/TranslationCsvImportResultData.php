<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Data;

use Spatie\LaravelData\Data;

final class TranslationCsvImportResultData extends Data
{
    public function __construct(
        public readonly int $importedCount,
        public readonly int $skippedCount,
    ) {}
}
