# Extension and action examples

<!-- Maintained by scripts/generate-package-readmes.php -->

Use the action functions with records and Data objects supplied by your application.
They pass each argument to the package operation and return its result.

These adapters show container registration. Use the owning package registry when
a contract requires contributor discovery.

Contract adapters wrap an existing implementation. Call their registration function
from your service provider with that implementation; tagged contracts keep their declared tag.
Resolve the backend by its concrete class before registration so the replacement contract
does not resolve itself. Static contract metadata uses one backend class per adapter.

## Contract `Capell\TranslationManager\Contracts\TranslationAITranslator`

<!-- example: contract Capell\TranslationManager\Contracts\TranslationAITranslator -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

final class TranslationAITranslatorAdapter implements \Capell\TranslationManager\Contracts\TranslationAITranslator
{
    public function __construct(private readonly \Capell\TranslationManager\Contracts\TranslationAITranslator $backend) {}

    #[\Override]
    public function available(): bool
    {
        return $this->backend->available();
    }

    #[\Override]
    public function translateSelected(string $sourceLocale, string $targetLocale, array $entries): array
    {
        return $this->backend->translateSelected($sourceLocale, $targetLocale, $entries);
    }
}

function registerTranslationAITranslatorAdapter(\Capell\TranslationManager\Contracts\TranslationAITranslator $backend): void
{
    app()->bind(TranslationAITranslatorAdapter::class, static fn (): TranslationAITranslatorAdapter => new TranslationAITranslatorAdapter($backend));
    app()->bind(\Capell\TranslationManager\Contracts\TranslationAITranslator::class, TranslationAITranslatorAdapter::class);
}
```

## Contract `Capell\TranslationManager\Contracts\TranslationFileStore`

<!-- example: contract Capell\TranslationManager\Contracts\TranslationFileStore -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

final class TranslationFileStoreAdapter implements \Capell\TranslationManager\Contracts\TranslationFileStore
{
    public function __construct(private readonly \Capell\TranslationManager\Contracts\TranslationFileStore $backend) {}

    #[\Override]
    public function comparison(\Capell\TranslationManager\Data\TranslationSourceData $source, string $fileKey, string $sourceLocale, string $targetLocale): array
    {
        return $this->backend->comparison($source, $fileKey, $sourceLocale, $targetLocale);
    }

    #[\Override]
    public function createLocale(\Capell\TranslationManager\Data\TranslationSourceData $source, string $locale, string $sourceLocale): void
    {
        $this->backend->createLocale($source, $locale, $sourceLocale);
    }

    #[\Override]
    public function duplicateLocale(\Capell\TranslationManager\Data\TranslationSourceData $source, string $fromLocale, string $targetLocale): void
    {
        $this->backend->duplicateLocale($source, $fromLocale, $targetLocale);
    }

    #[\Override]
    public function exportCsv(\Capell\TranslationManager\Data\TranslationSourceData $source, string $fileKey, string $sourceLocale, string $targetLocale): string
    {
        return $this->backend->exportCsv($source, $fileKey, $sourceLocale, $targetLocale);
    }

    #[\Override]
    public function files(\Capell\TranslationManager\Data\TranslationSourceData $source, string $sourceLocale, string $targetLocale): array
    {
        return $this->backend->files($source, $sourceLocale, $targetLocale);
    }

    #[\Override]
    public function importCsv(\Capell\TranslationManager\Data\TranslationSourceData $source, string $fileKey, string $locale, string $contents): \Capell\TranslationManager\Data\TranslationCsvImportResultData
    {
        return $this->backend->importCsv($source, $fileKey, $locale, $contents);
    }

    #[\Override]
    public function locales(\Capell\TranslationManager\Data\TranslationSourceData $source): array
    {
        return $this->backend->locales($source);
    }

    #[\Override]
    public function write(\Capell\TranslationManager\Data\TranslationWriteData $write): void
    {
        $this->backend->write($write);
    }
}

function registerTranslationFileStoreAdapter(\Capell\TranslationManager\Contracts\TranslationFileStore $backend): void
{
    app()->bind(TranslationFileStoreAdapter::class, static fn (): TranslationFileStoreAdapter => new TranslationFileStoreAdapter($backend));
    app()->bind(\Capell\TranslationManager\Contracts\TranslationFileStore::class, TranslationFileStoreAdapter::class);
}
```

## Contract `Capell\TranslationManager\Contracts\TranslationSourceResolver`

<!-- example: contract Capell\TranslationManager\Contracts\TranslationSourceResolver -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

final class TranslationSourceResolverAdapter implements \Capell\TranslationManager\Contracts\TranslationSourceResolver
{
    public function __construct(private readonly \Capell\TranslationManager\Contracts\TranslationSourceResolver $backend) {}

    #[\Override]
    public function source(string $key): \Capell\TranslationManager\Data\TranslationSourceData
    {
        return $this->backend->source($key);
    }

    #[\Override]
    public function sources(): array
    {
        return $this->backend->sources();
    }
}

function registerTranslationSourceResolverAdapter(\Capell\TranslationManager\Contracts\TranslationSourceResolver $backend): void
{
    app()->bind(TranslationSourceResolverAdapter::class, static fn (): TranslationSourceResolverAdapter => new TranslationSourceResolverAdapter($backend));
    app()->bind(\Capell\TranslationManager\Contracts\TranslationSourceResolver::class, TranslationSourceResolverAdapter::class);
}
```

## Action `buildLocalePublishReadiness`

<!-- example: action buildLocalePublishReadiness -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runBuildLocalePublishReadiness(string $sourceKey, string $sourceLocale, string $targetLocale): \Capell\TranslationManager\Data\LocalePublishReadinessData
{
    return \Capell\TranslationManager\Actions\BuildLocalePublishReadinessAction::run($sourceKey, $sourceLocale, $targetLocale);
}
```

## Action `buildTranslationMemorySuggestions`

<!-- example: action buildTranslationMemorySuggestions -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runBuildTranslationMemorySuggestions(string $sourceKey, string $sourceLocale, string $targetLocale, array $entries): array
{
    return \Capell\TranslationManager\Actions\BuildTranslationMemorySuggestionsAction::run($sourceKey, $sourceLocale, $targetLocale, $entries);
}
```

## Action `createLocaleFiles`

<!-- example: action createLocaleFiles -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runCreateLocaleFiles(string $sourceKey, string $locale, ?string $sourceLocale = null): void
{
    \Capell\TranslationManager\Actions\CreateLocaleFilesAction::run($sourceKey, $locale, $sourceLocale);
}
```

## Action `duplicateLocale`

<!-- example: action duplicateLocale -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runDuplicateLocale(string $sourceKey, string $fromLocale, string $targetLocale): void
{
    \Capell\TranslationManager\Actions\DuplicateLocaleAction::run($sourceKey, $fromLocale, $targetLocale);
}
```

## Action `exportTranslationEntriesToCsv`

<!-- example: action exportTranslationEntriesToCsv -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runExportTranslationEntriesToCsv(string $sourceKey, string $fileKey, string $sourceLocale, string $targetLocale): string
{
    return \Capell\TranslationManager\Actions\ExportTranslationEntriesToCsvAction::run($sourceKey, $fileKey, $sourceLocale, $targetLocale);
}
```

## Action `exportTranslationEntriesToPo`

<!-- example: action exportTranslationEntriesToPo -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runExportTranslationEntriesToPo(string $sourceKey, string $fileKey, string $sourceLocale, string $targetLocale): string
{
    return \Capell\TranslationManager\Actions\ExportTranslationEntriesToPoAction::run($sourceKey, $fileKey, $sourceLocale, $targetLocale);
}
```

## Action `exportTranslationEntriesToXliff`

<!-- example: action exportTranslationEntriesToXliff -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runExportTranslationEntriesToXliff(string $sourceKey, string $fileKey, string $sourceLocale, string $targetLocale): string
{
    return \Capell\TranslationManager\Actions\ExportTranslationEntriesToXliffAction::run($sourceKey, $fileKey, $sourceLocale, $targetLocale);
}
```

## Action `importTranslationEntriesFromCsv`

<!-- example: action importTranslationEntriesFromCsv -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runImportTranslationEntriesFromCsv(string $sourceKey, string $fileKey, string $locale, string $contents): \Capell\TranslationManager\Data\TranslationCsvImportResultData
{
    return \Capell\TranslationManager\Actions\ImportTranslationEntriesFromCsvAction::run($sourceKey, $fileKey, $locale, $contents);
}
```

## Action `importTranslationEntriesFromPo`

<!-- example: action importTranslationEntriesFromPo -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runImportTranslationEntriesFromPo(string $sourceKey, string $fileKey, string $locale, string $contents): \Capell\TranslationManager\Data\TranslationCsvImportResultData
{
    return \Capell\TranslationManager\Actions\ImportTranslationEntriesFromPoAction::run($sourceKey, $fileKey, $locale, $contents);
}
```

## Action `importTranslationEntriesFromXliff`

<!-- example: action importTranslationEntriesFromXliff -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runImportTranslationEntriesFromXliff(string $sourceKey, string $fileKey, string $locale, string $contents): \Capell\TranslationManager\Data\TranslationCsvImportResultData
{
    return \Capell\TranslationManager\Actions\ImportTranslationEntriesFromXliffAction::run($sourceKey, $fileKey, $locale, $contents);
}
```

## Action `listInstalledLocales`

<!-- example: action listInstalledLocales -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runListInstalledLocales(string $sourceKey): array
{
    return \Capell\TranslationManager\Actions\ListInstalledLocalesAction::run($sourceKey);
}
```

## Action `listTranslationFiles`

<!-- example: action listTranslationFiles -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runListTranslationFiles(string $sourceKey, string $sourceLocale, string $targetLocale): array
{
    return \Capell\TranslationManager\Actions\ListTranslationFilesAction::run($sourceKey, $sourceLocale, $targetLocale);
}
```

## Action `listTranslationSources`

<!-- example: action listTranslationSources -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runListTranslationSources(): array
{
    return \Capell\TranslationManager\Actions\ListTranslationSourcesAction::run();
}
```

## Action `loadTranslationComparison`

<!-- example: action loadTranslationComparison -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runLoadTranslationComparison(string $sourceKey, string $fileKey, string $sourceLocale, string $targetLocale): array
{
    return \Capell\TranslationManager\Actions\LoadTranslationComparisonAction::run($sourceKey, $fileKey, $sourceLocale, $targetLocale);
}
```

## Action `saveTranslationEntries`

<!-- example: action saveTranslationEntries -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runSaveTranslationEntries(string $sourceKey, string $fileKey, string $locale, array $values): void
{
    \Capell\TranslationManager\Actions\SaveTranslationEntriesAction::run($sourceKey, $fileKey, $locale, $values);
}
```

## Action `scanMissingTranslationKeys`

<!-- example: action scanMissingTranslationKeys -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runScanMissingTranslationKeys(string $sourceKey, string $locale): array
{
    return \Capell\TranslationManager\Actions\ScanMissingTranslationKeysAction::run($sourceKey, $locale);
}
```

## Action `translateSelectedEntries`

<!-- example: action translateSelectedEntries -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\TranslationManager;

function runTranslateSelectedEntries(string $sourceLocale, string $targetLocale, array $entries, array $selectedKeys, ?string $sourceKey = null): array
{
    return \Capell\TranslationManager\Actions\TranslateSelectedEntriesAction::run($sourceLocale, $targetLocale, $entries, $selectedKeys, $sourceKey);
}
```
