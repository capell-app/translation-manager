<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User;
use Override;

final class TranslationPermissionActor extends User
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /** @var list<string> */
    public array $granted = [];

    /**
     * @param  list<string>  $granted
     */
    public static function granting(array $granted): self
    {
        $actor = new self;
        $actor->granted = $granted;

        return $actor;
    }

    /** @param iterable<int, mixed> $abilities */
    #[Override]
    public function can($abilities, $arguments = []): bool
    {
        return is_string($abilities) && in_array($abilities, $this->granted, true);
    }
}
