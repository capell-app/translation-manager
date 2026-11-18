<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Exceptions;

use InvalidArgumentException;

/**
 * A translation failed a placeholder, plural form or glossary check. The message names the
 * key and the failed check and is safe to show to the editor.
 */
final class TranslationValidationException extends InvalidArgumentException {}
