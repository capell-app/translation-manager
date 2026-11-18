<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Contracts\TranslationFileStore;
use Capell\TranslationManager\Contracts\TranslationSourceResolver;
use Capell\TranslationManager\Data\TranslationCsvImportResultData;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static TranslationCsvImportResultData run(string $sourceKey, string $fileKey, string $locale, string $contents, bool $dryRun = false)
 */
final class ImportTranslationEntriesFromCsvAction
{
    use AsFake;
    use AsObject;

    public function handle(string $sourceKey, string $fileKey, string $locale, string $contents, bool $dryRun = false): TranslationCsvImportResultData
    {
        $source = resolve(TranslationSourceResolver::class)->source($sourceKey);

        return resolve(TranslationFileStore::class)->importCsv($source, $fileKey, $locale, $contents, $dryRun);
    }
}
