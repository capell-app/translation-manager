<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Tests\Fixtures;

use Illuminate\Filesystem\Filesystem;

final class CountingFilesystem extends Filesystem
{
    public int $allFilesCalls = 0;

    public int $lastModifiedCalls = 0;

    public function allFiles(mixed $directory, mixed $hidden = false): array
    {
        $this->allFilesCalls++;

        return parent::allFiles($directory, $hidden);
    }

    public function lastModified(mixed $path): int
    {
        $this->lastModifiedCalls++;

        return parent::lastModified($path);
    }
}
