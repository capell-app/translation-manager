<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Data;

use Spatie\LaravelData\Data;

final class TranslationQueueItemData extends Data
{
    public function __construct(
        public readonly string $fileKey,
        public readonly string $fileLabel,
        public readonly string $key,
        public readonly string $status,
    ) {}
}
