<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Contracts\TranslationSourceResolver;
use Capell\TranslationManager\Data\MissingTranslationKeyData;
use Illuminate\Filesystem\Filesystem;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use SplFileInfo;

final class ScanMissingTranslationKeysAction
{
    use AsFake;
    use AsObject;

    /**
     * @return array<int, MissingTranslationKeyData>
     */
    public function handle(string $sourceKey, string $locale): array
    {
        $knownKeys = $this->knownTranslationKeys($sourceKey, $locale);
        $source = resolve(TranslationSourceResolver::class)->source($sourceKey);
        $sourcePrefix = $source->namespace !== null ? $source->namespace . '::' : '';

        return collect($this->referencedTranslationKeys())
            ->filter(fn (MissingTranslationKeyData $reference): bool => $sourcePrefix === ''
                ? ! str_contains($reference->key, '::')
                : str_starts_with($reference->key, $sourcePrefix))
            ->reject(fn (MissingTranslationKeyData $reference): bool => in_array($reference->key, $knownKeys, true))
            ->unique(fn (MissingTranslationKeyData $reference): string => $reference->key . '|' . $reference->path . '|' . $reference->line)
            ->sortBy(fn (MissingTranslationKeyData $reference): string => $reference->key)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function knownTranslationKeys(string $sourceKey, string $locale): array
    {
        $source = resolve(TranslationSourceResolver::class)->source($sourceKey);
        $prefix = $source->namespace !== null ? $source->namespace . '::' : '';
        $keys = [];

        foreach (ListTranslationFilesAction::run($sourceKey, $locale, $locale) as $file) {
            foreach (LoadTranslationComparisonAction::run($sourceKey, $file->key, $locale, $locale) as $entry) {
                $keys[] = $file->key === 'json'
                    ? $prefix . $entry->key
                    : $prefix . substr($file->key, 4) . '.' . $entry->key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<int, MissingTranslationKeyData>
     */
    private function referencedTranslationKeys(): array
    {
        $references = [];
        $filesystem = resolve(Filesystem::class);

        foreach ($this->scanPaths() as $scanPath) {
            if (! $filesystem->isDirectory($scanPath)) {
                continue;
            }

            foreach ($filesystem->allFiles($scanPath) as $file) {
                if (! $file instanceof SplFileInfo) {
                    continue;
                }

                if (! $this->isScannableFile($file)) {
                    continue;
                }

                array_push($references, ...$this->referencesInFile($file));
            }
        }

        return $references;
    }

    /**
     * @return list<string>
     */
    private function scanPaths(): array
    {
        $paths = config('capell-translation-manager.scan_paths', []);

        if (! is_array($paths)) {
            return [];
        }

        $scanPaths = [];

        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                $scanPaths[] = $path;
            }
        }

        return $scanPaths;
    }

    private function isScannableFile(SplFileInfo $file): bool
    {
        return in_array($file->getExtension(), ['php', 'blade.php'], true)
            || str_ends_with($file->getFilename(), '.blade.php');
    }

    /**
     * @return array<int, MissingTranslationKeyData>
     */
    private function referencesInFile(SplFileInfo $file): array
    {
        $contents = file_get_contents($file->getPathname());

        if (! is_string($contents) || $contents === '') {
            return [];
        }

        preg_match_all('/(?:__|trans|trans_choice|@lang)\(\s*([\'"])([^\'"]+)\1/', $contents, $matches, PREG_OFFSET_CAPTURE);
        $references = [];

        foreach ($matches[2] as [$key, $offset]) {
            $references[] = new MissingTranslationKeyData(
                key: $key,
                path: $file->getPathname(),
                line: substr_count(substr($contents, 0, $offset), "\n") + 1,
            );
        }

        return $references;
    }
}
