<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Contracts\TranslationFileStore;
use Capell\TranslationManager\Contracts\TranslationSourceResolver;
use Capell\TranslationManager\Data\TranslationCsvImportResultData;
use Lorisleiva\Actions\Concerns\AsObject;

final class ImportTranslationEntriesFromCsvAction
{
    use AsObject;

    public function handle(string $sourceKey, string $fileKey, string $locale, string $contents): TranslationCsvImportResultData
    {
        $source = resolve(TranslationSourceResolver::class)->source($sourceKey);

        return resolve(TranslationFileStore::class)->importCsv($source, $fileKey, $locale, $contents);
    }
}
