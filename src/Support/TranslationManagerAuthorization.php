<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Support;

use Capell\TranslationManager\Enums\TranslationManagerPermission;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Translation files are executable PHP, so reading and writing them are separate permissions.
 */
final class TranslationManagerAuthorization
{
    public static function canView(): bool
    {
        return self::has(TranslationManagerPermission::View) || self::canManage();
    }

    public static function canManage(): bool
    {
        return self::has(TranslationManagerPermission::Manage);
    }

    /**
     * @throws AuthorizationException
     */
    public static function authorizeManage(): void
    {
        throw_unless(self::canManage(), AuthorizationException::class);
    }

    private static function has(TranslationManagerPermission $permission): bool
    {
        return auth()->user()?->can($permission->value) === true;
    }
}
