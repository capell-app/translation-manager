<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Exceptions;

use RuntimeException;
use Throwable;

final class TranslationFileWriteException extends RuntimeException
{
    public static function lockFailed(string $path, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Unable to lock translation file [%s] for writing.', $path),
            previous: $previous,
        );
    }

    public static function encodingFailed(string $path, Throwable $previous): self
    {
        return new self(
            sprintf('Unable to encode translation file [%s].', $path),
            previous: $previous,
        );
    }

    public static function readingFailed(string $path, Throwable $previous): self
    {
        return new self(
            sprintf('Unable to read existing translation file [%s].', $path),
            previous: $previous,
        );
    }

    public static function stagingFailed(string $path, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Unable to stage translation file [%s].', $path),
            previous: $previous,
        );
    }

    public static function validationFailed(string $path, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Unable to validate staged translation file [%s].', $path),
            previous: $previous,
        );
    }

    public static function publicationFailed(string $path, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Unable to publish translation file [%s].', $path),
            previous: $previous,
        );
    }

    public static function rollbackFailed(string $path, Throwable $previous): self
    {
        return new self(
            sprintf('Unable to restore translation file [%s] after a failed publication.', $path),
            previous: $previous,
        );
    }
}
