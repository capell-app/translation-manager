<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Enums;

enum TranslationManagerPermission: string
{
    case View = 'View:TranslationManagerPage';
    case Manage = 'Manage:TranslationManagerPage';

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }
}
