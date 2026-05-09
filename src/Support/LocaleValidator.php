<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Support;

use InvalidArgumentException;

final class LocaleValidator
{
    public function assertValid(string $locale): void
    {
        if (! $this->isValid($locale)) {
            throw new InvalidArgumentException(sprintf('Locale [%s] is not allowed.', $locale));
        }
    }

    public function isValid(string $locale): bool
    {
        $pattern = config('capell-translation-manager.locale_pattern', '/^[a-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/');

        return is_string($pattern) && preg_match($pattern, $locale) === 1;
    }
}
