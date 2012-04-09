<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Tests\Fixtures;

use Illuminate\Filesystem\Filesystem;
use Override;
use RuntimeException;

final class PauseAfterTranslationPublishFilesystem extends Filesystem
{
    private bool $paused = false;

    public function __construct(
        private readonly string $translationPath,
        private readonly string $readyPath,
        private readonly string $releasePath,
    ) {}

    #[Override]
    public function move(mixed $path, mixed $target): bool
    {
        $moved = parent::move($path, $target);

        if ($moved && ! $this->paused && $target === $this->translationPath) {
            $this->paused = true;

            // Block the child without polling while the parent inspects and kills it.
            if (! posix_mkfifo($this->releasePath, 0600)) {
                throw new RuntimeException('Unable to create the translation publication pause pipe.');
            }

            if (file_put_contents($this->readyPath, 'ready', LOCK_EX) === false) {
                throw new RuntimeException('Unable to signal a paused translation publication.');
            }

            $release = fopen($this->releasePath, 'r');

            if ($release === false) {
                throw new RuntimeException('Unable to wait for translation publication release.');
            }

            fclose($release);
        }

        return $moved;
    }
}
