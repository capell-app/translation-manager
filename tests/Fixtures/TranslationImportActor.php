<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User;
use Override;

final class TranslationImportActor extends User
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /** @param iterable<int, mixed> $abilities */
    #[Override]
    public function can($abilities, $arguments = []): bool
    {
        return true;
    }
}
