<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Data;

use Spatie\LaravelData\Data;

final class MissingTranslationKeyData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $path,
        public readonly int $line,
    ) {}
}
