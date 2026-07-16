<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Contracts\TranslationFileStore;
use Capell\TranslationManager\Contracts\TranslationSourceResolver;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class ExportTranslationEntriesToCsvAction
{
    use AsFake;
    use AsObject;

    public function handle(string $sourceKey, string $fileKey, string $sourceLocale, string $targetLocale): string
    {
        $source = resolve(TranslationSourceResolver::class)->source($sourceKey);

        return resolve(TranslationFileStore::class)->exportCsv($source, $fileKey, $sourceLocale, $targetLocale);
    }
}
