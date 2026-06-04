# Sources, Stores, And AI

Translation Manager reads translation sources, compares locale files, writes selected entries, and optionally asks AI for suggestions. The package binds three contracts by default:

- `TranslationSourceResolver` -> `ConfigTranslationSourceResolver`
- `TranslationFileStore` -> `FileTranslationFileStore`
- `TranslationAITranslator` -> `NullTranslationAITranslator`

## Config Keys

| Key                                                | Use                                                 |
| -------------------------------------------------- | --------------------------------------------------- |
| `capell-translation-manager.source_locale`         | Default source locale.                              |
| `capell-translation-manager.locale_pattern`        | Allowed locale format.                              |
| `capell-translation-manager.app_source`            | Application translation source.                     |
| `capell-translation-manager.package_paths`         | Glob paths scanned for package translation folders. |
| `capell-translation-manager.vendor_namespaces`     | Additional vendor namespaces.                       |
| `capell-translation-manager.package_source_writes` | Allows writes back to package sources.              |
| `capell-translation-manager.scan_paths`            | Paths scanned for missing translation key usages.   |
| `capell-translation-manager.glossary`              | Locale-specific source-term to target-term mapping. |

Keep `package_source_writes` false unless the host app intentionally edits package language files.

## Override Merge Behaviour

Package and vendor translation reads merge source files with Laravel override files. The override file wins for keys it contains, and missing override keys continue to appear from the source file. This matches Laravel's runtime expectation for package translation overrides and prevents partial overrides from hiding source keys in the editor.

Writes still use Laravel override paths for package and vendor sources unless `package_source_writes` is true. Saving all visible rows may materialize source keys into the override file, but package source files remain untouched by default.

## Save Validation

When a non-empty target value is saved or imported, Translation Manager compares it with the configured source locale:

- source placeholders such as `:count` must still appear in the target value;
- plural strings using pipe delimiters must keep the same number of plural forms;
- blank target values are allowed and remain `missing`.

The store also records per-key source hashes in a `*.capell-meta.json` sidecar when target files are saved or imported. Comparisons use those hashes for key-level stale detection. Files that predate the metadata continue using file modification times until they are saved again.

Comparisons also understand Laravel's configured fallback locale. If the target locale lacks a key and the fallback locale has a non-empty value, the entry is marked `fallback` instead of `missing` so translators can separate true gaps from runtime-covered strings.

## Missing Key Scans

`ScanMissingTranslationKeysAction` scans configured paths for literal `__()`, `trans()`, `trans_choice()`, and `@lang()` calls. It compares references against the selected source locale. PHP files are matched as `file.key` and JSON translations are matched by their literal key.

Use this for deterministic checks of obvious missing keys. Dynamic keys are intentionally not guessed; they should be covered by tests or explicit language-file entries.

## PO / Gettext

PO export writes each entry with `msgctxt` set to the Laravel translation key, `msgid` set to the source value, and `msgstr` set to the target value. Import reads `msgctxt` and `msgstr` back into the selected file. The same placeholder and plural validation used by CSV/XLIFF imports applies to PO imports.

## Add Translation Sources

The default resolver reads config. Override `TranslationSourceResolver` only when sources come from another registry.

```php
use Capell\TranslationManager\Contracts\TranslationSourceResolver;
use Capell\TranslationManager\Data\TranslationSourceData;

final class DemoTranslationSourceResolver implements TranslationSourceResolver
{
    public function sources(): array
    {
        return [
            new TranslationSourceData(
                key: 'demo',
                label: 'Demo',
                sourcePath: base_path('packages/demo/resources/lang'),
                overridePath: resource_path('lang/vendor/demo'),
                namespace: 'demo',
                type: 'package',
                sourceWritable: false,
            ),
        ];
    }

    public function source(string $key): TranslationSourceData
    {
        return collect($this->sources())->firstOrFail(
            static fn (TranslationSourceData $source): bool => $source->key === $key,
        );
    }
}
```

## Add an AI Translator

```php
use Capell\TranslationManager\Contracts\TranslationAITranslator;
use Capell\TranslationManager\Data\AITranslationSuggestionData;
use Capell\TranslationManager\Data\TranslationEntryData;

final class DemoTranslationAITranslator implements TranslationAITranslator
{
    public function available(): bool
    {
        return true;
    }

    public function translateSelected(string $sourceLocale, string $targetLocale, array $entries): array
    {
        return array_map(
            static fn (TranslationEntryData $entry): AITranslationSuggestionData => new AITranslationSuggestionData(
                key: $entry->key,
                value: '[' . $targetLocale . '] ' . $entry->sourceValue,
            ),
            $entries,
        );
    }
}

$this->app->singleton(TranslationAITranslator::class, DemoTranslationAITranslator::class);
```

Use the AI Orchestrator integration when it is installed. Bind a custom translator only when another provider owns translation. AI suggestions remain pending until an editor explicitly accepts them in the Translation Manager page.

Before AI runs, `BuildTranslationMemorySuggestionsAction` checks existing translated entries for exact source-string matches and suggests the previous target value. This keeps repeated phrases consistent without calling an external translator.

## Glossary

Configure glossary terms by target locale:

```php
'glossary' => [
    'fr' => [
        'CMS' => 'SGC',
    ],
],
```

When a source value contains `CMS`, a non-empty French target value must contain `SGC`. The same validation runs for direct saves and CSV, XLIFF, or PO imports.

## Verification

```bash
vendor/bin/pest packages/translation-manager/tests --configuration=phpunit.xml
```
