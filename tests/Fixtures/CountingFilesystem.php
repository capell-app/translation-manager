<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Tests\Fixtures;

use Illuminate\Filesystem\Filesystem;
use Override;

final class CountingFilesystem extends Filesystem
{
    public int $allFilesCalls = 0;

    public int $lastModifiedCalls = 0;

    #[Override]
    public function allFiles(mixed $directory, mixed $hidden = false): array
    {
        $this->allFilesCalls++;

        return parent::allFiles($directory, $hidden);
    }

    #[Override]
    public function lastModified(mixed $path): int
    {
        $this->lastModifiedCalls++;

        return parent::lastModified($path);
    }
}
