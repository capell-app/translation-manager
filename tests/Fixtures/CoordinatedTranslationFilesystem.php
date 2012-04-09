<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Tests\Fixtures;

use Illuminate\Filesystem\Filesystem;
use Override;
use RuntimeException;

final class CoordinatedTranslationFilesystem extends Filesystem
{
    private bool $coordinated = false;

    public function __construct(
        private readonly string $readyPath,
        private readonly string $releasePath,
    ) {}

    #[Override]
    public function put(mixed $path, mixed $contents, mixed $lock = false): int|false
    {
        if (! $this->coordinated && is_string($path) && str_contains($path, '.tmp.')) {
            $this->coordinated = true;

            if (! posix_mkfifo($this->releasePath, 0600)) {
                throw new RuntimeException('Unable to create the coordinated translation release pipe.');
            }

            if (file_put_contents($this->readyPath, 'ready', LOCK_EX) === false) {
                throw new RuntimeException('Unable to signal a coordinated translation write.');
            }

            $release = fopen($this->releasePath, 'r+');

            if ($release === false) {
                throw new RuntimeException('Unable to open the coordinated translation release pipe.');
            }

            try {
                $read = [$release];
                $write = [];
                $except = [];

                if (stream_select($read, $write, $except, 10) !== 1 || fread($release, 1) === '') {
                    throw new RuntimeException('Timed out waiting to release a coordinated translation write.');
                }
            } finally {
                fclose($release);
            }
        }

        $result = parent::put($path, $contents, $lock);

        return is_int($result) ? $result : false;
    }
}
