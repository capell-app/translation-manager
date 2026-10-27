<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Data;

use Livewire\Wireable;
use Spatie\LaravelData\Concerns\WireableData;
use Spatie\LaravelData\Data;

final class TranslationImportData extends Data implements Wireable
{
    use WireableData;

    public function __construct(
        public readonly string $format,
        public readonly string $contents,
    ) {}
}
