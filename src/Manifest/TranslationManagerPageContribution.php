<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class TranslationManagerPageContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }
}
