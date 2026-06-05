<?php

declare(strict_types=1);

use Capell\TranslationManager\Actions\BuildLocalePublishReadinessAction;
use Capell\TranslationManager\Actions\BuildTranslationMemorySuggestionsAction;
use Capell\TranslationManager\Actions\CreateLocaleFilesAction;
use Capell\TranslationManager\Actions\DuplicateLocaleAction;
use Capell\TranslationManager\Actions\ExportTranslationEntriesToCsvAction;
use Capell\TranslationManager\Actions\ExportTranslationEntriesToPoAction;
use Capell\TranslationManager\Actions\ExportTranslationEntriesToXliffAction;
use Capell\TranslationManager\Actions\ImportTranslationEntriesFromCsvAction;
use Capell\TranslationManager\Actions\ImportTranslationEntriesFromPoAction;
use Capell\TranslationManager\Actions\ImportTranslationEntriesFromXliffAction;
use Capell\TranslationManager\Actions\ListInstalledLocalesAction;
use Capell\TranslationManager\Actions\ListTranslationFilesAction;
use Capell\TranslationManager\Actions\ListTranslationSourcesAction;
use Capell\TranslationManager\Actions\LoadTranslationComparisonAction;
use Capell\TranslationManager\Actions\SaveTranslationEntriesAction;
use Capell\TranslationManager\Actions\ScanMissingTranslationKeysAction;
use Capell\TranslationManager\Contracts\TranslationFileStore;
use Capell\TranslationManager\Data\TranslationEntryData;
use Capell\TranslationManager\Support\FileTranslationFileStore;
use Capell\TranslationManager\Support\LocaleValidator;
use Capell\TranslationManager\Tests\Fixtures\CountingFilesystem;
use Capell\TranslationManager\Tests\Fixtures\PackageTranslationFixtureServiceProvider;
use Capell\TranslationManager\Tests\TranslationManagerTestCase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(TranslationManagerTestCase::class);

beforeEach(function (): void {
    $this->translationBasePath = sys_get_temp_dir() . '/capell-translation-manager/' . Str::uuid()->toString();
    $this->appLanguagePath = $this->translationBasePath . '/app-lang';
    $this->packagePath = $this->translationBasePath . '/fixture-package';

    File::ensureDirectoryExists($this->appLanguagePath . '/en');
    File::ensureDirectoryExists($this->appLanguagePath . '/fr');
    File::ensureDirectoryExists($this->packagePath . '/resources/lang/en');

    config()->set('capell-translation-manager.app_source.path', $this->appLanguagePath);
    config()->set('capell-translation-manager.package_paths', [$this->packagePath . '/resources/lang']);
    config()->set('capell-translation-manager.package_source_writes', false);

    File::put($this->appLanguagePath . '/en/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'title' => 'Hello',
    'nested' => [
        'body' => 'Welcome',
        'count' => 10,
    ],
];
PHP);

    File::put($this->appLanguagePath . '/fr/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'title' => 'Bonjour',
];
PHP);

    File::put($this->appLanguagePath . '/en.json', json_encode([
        'Plain string' => 'Plain string',
        'Shared button' => 'Shared button',
        'Sentence.with.dot' => 'Sentence with dot',
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    File::put($this->packagePath . '/composer.json', json_encode([
        'name' => 'capell-app/fixture-package',
        'extra' => [
            'laravel' => [
                'providers' => [
                    PackageTranslationFixtureServiceProvider::class,
                ],
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    File::put($this->packagePath . '/resources/lang/en/package.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'heading' => 'Package heading',
];
PHP);
});

afterEach(function (): void {
    File::deleteDirectory($this->translationBasePath);
    File::deleteDirectory(lang_path('vendor/capell-app-fixture-package'));
    File::deleteDirectory(lang_path('vendor/capell-fixture-package'));
});

it('discovers app and package translation sources', function (): void {
    $sources = collect(ListTranslationSourcesAction::run());

    expect($sources->pluck('key')->all())
        ->toContain('app')
        ->toContain('package:capell-app/fixture-package');

    $packageSource = $sources->firstWhere('key', 'package:capell-app/fixture-package');

    throw_if($packageSource === null, RuntimeException::class, 'Expected package translation source to exist.');

    expect($packageSource->namespace)->toBe('capell-fixture-package')
        ->and($packageSource->sourceWritable)->toBeFalse();
});

it('lists locales and compares source and target entries', function (): void {
    $locales = ListInstalledLocalesAction::run('app');
    $files = ListTranslationFilesAction::run('app', 'en', 'fr');
    $entries = collect(LoadTranslationComparisonAction::run('app', 'php:messages', 'en', 'fr'));
    $titleEntry = $entries->firstWhere('key', 'title');
    $bodyEntry = $entries->firstWhere('key', 'nested.body');
    $countEntry = $entries->firstWhere('key', 'nested.count');

    throw_if($titleEntry === null || $bodyEntry === null || $countEntry === null, RuntimeException::class, 'Expected compared translation entries to exist.');

    expect(collect($locales)->pluck('locale')->all())->toContain('en', 'fr')
        ->and(collect($files)->pluck('key')->all())->toContain('php:messages', 'json')
        ->and(collect($files)->firstWhere('key', 'json')?->label)->toBe(__('capell-translation-manager::package.json_translations'))
        ->and($titleEntry->status)->toBe('changed')
        ->and($bodyEntry->status)->toBe('missing')
        ->and($countEntry->editable)->toBeFalse();
});

it('marks translated entries stale when the source file is newer than the target file', function (): void {
    $sourcePath = $this->appLanguagePath . '/en/messages.php';
    $targetPath = $this->appLanguagePath . '/fr/messages.php';

    touch($targetPath, Date::now()->subMinutes(2)->getTimestamp());
    touch($sourcePath, Date::now()->getTimestamp());

    $entries = collect(LoadTranslationComparisonAction::run('app', 'php:messages', 'en', 'fr'));
    $titleEntry = $entries->firstWhere('key', 'title');

    throw_if($titleEntry === null, RuntimeException::class, 'Expected compared translation entry to exist.');

    expect($titleEntry->status)->toBe('stale');
});

it('uses per-key source hashes to avoid stale noise from unrelated source edits', function (): void {
    SaveTranslationEntriesAction::run('app', 'php:messages', 'fr', [
        'title' => 'Bonjour',
        'nested.body' => 'Bienvenue',
    ]);

    File::put($this->appLanguagePath . '/en/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'title' => 'Hello',
    'nested' => [
        'body' => 'Welcome updated',
        'count' => 10,
    ],
];
PHP);

    touch($this->appLanguagePath . '/en/messages.php', Date::now()->getTimestamp());
    touch($this->appLanguagePath . '/fr/messages.php', Date::now()->subMinutes(2)->getTimestamp());

    $entries = collect(LoadTranslationComparisonAction::run('app', 'php:messages', 'en', 'fr'));

    expect($entries->firstWhere('key', 'title')?->status)->toBe('changed')
        ->and($entries->firstWhere('key', 'nested.body')?->status)->toBe('stale');
});

it('keeps changed status when the target file is newer than the source file', function (): void {
    $sourcePath = $this->appLanguagePath . '/en/messages.php';
    $targetPath = $this->appLanguagePath . '/fr/messages.php';

    touch($sourcePath, Date::now()->subMinutes(2)->getTimestamp());
    touch($targetPath, Date::now()->getTimestamp());

    $entries = collect(LoadTranslationComparisonAction::run('app', 'php:messages', 'en', 'fr'));
    $titleEntry = $entries->firstWhere('key', 'title');

    throw_if($titleEntry === null, RuntimeException::class, 'Expected compared translation entry to exist.');

    expect($titleEntry->status)->toBe('changed');
});

it('memoizes file listings and comparisons until translation files are written', function (): void {
    $filesystem = new CountingFilesystem;

    app()->forgetInstance(TranslationFileStore::class);
    app()->singleton(
        TranslationFileStore::class,
        static fn (): FileTranslationFileStore => new FileTranslationFileStore($filesystem, app(LocaleValidator::class)),
    );

    ListTranslationFilesAction::run('app', 'en', 'fr');
    $allFilesCalls = $filesystem->allFilesCalls;

    ListTranslationFilesAction::run('app', 'en', 'fr');

    expect($filesystem->allFilesCalls)->toBe($allFilesCalls);

    $entries = collect(LoadTranslationComparisonAction::run('app', 'php:messages', 'en', 'fr'));
    $lastModifiedCalls = $filesystem->lastModifiedCalls;

    LoadTranslationComparisonAction::run('app', 'php:messages', 'en', 'fr');

    expect($filesystem->lastModifiedCalls)->toBe($lastModifiedCalls)
        ->and($entries->firstWhere('key', 'title')?->targetValue)->toBe('Bonjour');

    SaveTranslationEntriesAction::run('app', 'php:messages', 'fr', [
        'title' => 'Salut',
    ]);

    ListTranslationFilesAction::run('app', 'en', 'fr');

    $entries = collect(LoadTranslationComparisonAction::run('app', 'php:messages', 'en', 'fr'));

    expect($filesystem->allFilesCalls)->toBeGreaterThan($allFilesCalls)
        ->and($entries->firstWhere('key', 'title')?->targetValue)->toBe('Salut');
});

it('marks missing target values as covered when Laravel fallback locale has a value', function (): void {
    config()->set('app.fallback_locale', 'fr');

    $entries = collect(LoadTranslationComparisonAction::run('app', 'php:messages', 'en', 'de'));
    $readiness = BuildLocalePublishReadinessAction::run('app', 'en', 'de');

    expect($entries->firstWhere('key', 'title')?->status)->toBe('fallback')
        ->and($readiness->statusCounts['fallback'])->toBe(1)
        ->and($readiness->statusCounts['missing'])->toBeGreaterThanOrEqual(1);
});

it('scans application code for referenced translation keys missing from language files', function (): void {
    $scanPath = $this->translationBasePath . '/views';
    File::ensureDirectoryExists($scanPath);
    File::put($scanPath . '/welcome.blade.php', <<<'BLADE'
{{ __('messages.title') }}
{{ __('messages.missing') }}
{{ __('Plain string') }}
{{ __('capell-fixture-package::package.missing') }}
@lang('messages.nested.body')
BLADE);

    config()->set('capell-translation-manager.scan_paths', [$scanPath]);

    $missingKeys = ScanMissingTranslationKeysAction::run('app', 'en');

    expect(array_map(static fn ($missingKey): string => $missingKey->key, $missingKeys))->toBe(['messages.missing'])
        ->and($missingKeys[0]->line)->toBe(2);
});

it('saves app language files in place while preserving unedited entries', function (): void {
    SaveTranslationEntriesAction::run('app', 'php:messages', 'fr', [
        'nested.body' => 'Bienvenue',
    ]);

    $values = require $this->appLanguagePath . '/fr/messages.php';

    expect($values['title'])->toBe('Bonjour')
        ->and($values['nested']['body'])->toBe('Bienvenue');
});

it('creates blank app locale files from source keys', function (): void {
    CreateLocaleFilesAction::run('app', 'es', 'en');

    $phpValues = require $this->appLanguagePath . '/es/messages.php';
    $jsonValues = json_decode(File::get($this->appLanguagePath . '/es.json'), true);

    expect($phpValues)->toBe([
        'title' => '',
        'nested' => [
            'body' => '',
        ],
    ])->and($jsonValues)->toBe([
        'Plain string' => '',
        'Shared button' => '',
        'Sentence.with.dot' => '',
    ]);
});

it('saves JSON language keys literally even when keys contain dots', function (): void {
    SaveTranslationEntriesAction::run('app', 'json', 'fr', [
        'Sentence.with.dot' => 'Phrase avec point',
    ]);

    $jsonValues = json_decode(File::get($this->appLanguagePath . '/fr.json'), true);

    expect($jsonValues)->toHaveKey('Sentence.with.dot')
        ->and($jsonValues['Sentence.with.dot'])->toBe('Phrase avec point')
        ->and($jsonValues)->not->toHaveKey('Sentence');
});

it('exports compared translation entries as CSV', function (): void {
    $csv = ExportTranslationEntriesToCsvAction::run('app', 'php:messages', 'en', 'fr');
    $rows = array_map(
        str_getcsv(...),
        explode(PHP_EOL, trim((string) $csv)),
    );

    expect($rows[0])->toBe(['key', 'source_value', 'target_value', 'status'])
        ->and($rows)->toContain(['nested.body', 'Welcome', '', 'missing'])
        ->and($rows)->toContain(['title', 'Hello', 'Bonjour', 'changed']);
});

it('imports target translation values from CSV through the file store', function (): void {
    $csv = <<<'CSV'
key,target_value
nested.body,Bienvenue
title,Salut
,"Ignored blank key"
CSV;

    $result = ImportTranslationEntriesFromCsvAction::run('app', 'php:messages', 'fr', $csv);
    $values = require $this->appLanguagePath . '/fr/messages.php';

    expect($result->importedCount)->toBe(2)
        ->and($result->skippedCount)->toBe(1)
        ->and($values['title'])->toBe('Salut')
        ->and($values['nested']['body'])->toBe('Bienvenue');
});

it('imports JSON target translation values from CSV without splitting dotted keys', function (): void {
    $csv = <<<'CSV'
key,target_value
Sentence.with.dot,Phrase avec point
CSV;

    ImportTranslationEntriesFromCsvAction::run('app', 'json', 'fr', $csv);

    $jsonValues = json_decode(File::get($this->appLanguagePath . '/fr.json'), true);

    expect($jsonValues)->toHaveKey('Sentence.with.dot')
        ->and($jsonValues['Sentence.with.dot'])->toBe('Phrase avec point')
        ->and($jsonValues)->not->toHaveKey('Sentence');
});

it('exports compared translation entries as XLIFF', function (): void {
    $xliff = ExportTranslationEntriesToXliffAction::run('app', 'php:messages', 'en', 'fr');

    expect($xliff)->toContain('<xliff version="1.2">')
        ->and($xliff)->toContain('source-language="en"')
        ->and($xliff)->toContain('target-language="fr"')
        ->and($xliff)->toContain('<trans-unit id="nested.body" resname="nested.body">')
        ->and($xliff)->toContain('<source>Welcome</source>')
        ->and($xliff)->toContain('<target state="missing"></target>');
});

it('imports target translation values from XLIFF', function (): void {
    $xliff = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xliff version="1.2">
  <file source-language="en" target-language="fr" datatype="plaintext" original="app:php:messages">
    <body>
      <trans-unit id="nested.body" resname="nested.body">
        <source>Welcome</source>
        <target>Bienvenue</target>
      </trans-unit>
      <trans-unit id="title" resname="title">
        <source>Hello</source>
        <target>Salut</target>
      </trans-unit>
      <trans-unit id="">
        <source>Ignored</source>
        <target>Ignored</target>
      </trans-unit>
    </body>
  </file>
</xliff>
XML;

    $result = ImportTranslationEntriesFromXliffAction::run('app', 'php:messages', 'fr', $xliff);
    $values = require $this->appLanguagePath . '/fr/messages.php';

    expect($result->importedCount)->toBe(2)
        ->and($result->skippedCount)->toBe(1)
        ->and($values['title'])->toBe('Salut')
        ->and($values['nested']['body'])->toBe('Bienvenue');
});

it('exports and imports target translation values as PO gettext', function (): void {
    $po = ExportTranslationEntriesToPoAction::run('app', 'php:messages', 'en', 'fr');

    expect($po)->toContain('msgctxt "nested.body"')
        ->and($po)->toContain('msgid "Welcome"')
        ->and($po)->toContain('msgstr ""');

    $result = ImportTranslationEntriesFromPoAction::run('app', 'php:messages', 'fr', <<<'PO'
msgctxt "nested.body"
msgid "Welcome"
msgstr "Bienvenue"

msgctxt "title"
msgid "Hello"
msgstr "Salut"

msgid "Ignored"
msgstr "Ignored"
PO);

    $values = require $this->appLanguagePath . '/fr/messages.php';

    expect($result->importedCount)->toBe(2)
        ->and($result->skippedCount)->toBe(1)
        ->and($values['title'])->toBe('Salut')
        ->and($values['nested']['body'])->toBe('Bienvenue');
});

it('builds locale publish readiness from missing and stale entries', function (): void {
    $sourcePath = $this->appLanguagePath . '/en/messages.php';
    $targetPath = $this->appLanguagePath . '/fr/messages.php';

    touch($targetPath, Date::now()->subMinutes(2)->getTimestamp());
    touch($sourcePath, Date::now()->getTimestamp());

    $readiness = BuildLocalePublishReadinessAction::run('app', 'en', 'fr');

    expect($readiness->ready)->toBeFalse()
        ->and($readiness->fileCount)->toBe(2)
        ->and($readiness->entryCount)->toBeGreaterThanOrEqual(5)
        ->and($readiness->statusCounts['missing'])->toBeGreaterThanOrEqual(1)
        ->and($readiness->statusCounts['stale'])->toBeGreaterThanOrEqual(1);

    SaveTranslationEntriesAction::run('app', 'php:messages', 'fr', [
        'title' => 'Bonjour',
        'nested.body' => 'Bienvenue',
    ]);
    SaveTranslationEntriesAction::run('app', 'json', 'fr', [
        'Plain string' => 'Texte simple',
        'Shared button' => 'Bouton',
        'Sentence.with.dot' => 'Phrase avec point',
    ]);
    touch($sourcePath, Date::now()->subMinutes(2)->getTimestamp());
    touch($targetPath, Date::now()->getTimestamp());

    $readiness = BuildLocalePublishReadinessAction::run('app', 'en', 'fr');

    expect($readiness->ready)->toBeTrue()
        ->and($readiness->statusCounts['missing'])->toBe(0)
        ->and($readiness->statusCounts['stale'])->toBe(0);
});

it('rejects CSV imports without required columns', function (): void {
    ImportTranslationEntriesFromCsvAction::run('app', 'php:messages', 'fr', "key,value\nnested.body,Bienvenue\n");
})->throws(InvalidArgumentException::class);

it('rejects translation file keys that escape the locale directory', function (): void {
    SaveTranslationEntriesAction::run('app', 'php:../escape', 'fr', [
        'title' => 'Escaped',
    ]);
})->throws(InvalidArgumentException::class);

it('rejects locale names that escape the language directory while reading', function (): void {
    LoadTranslationComparisonAction::run('app', 'php:messages', '../en', 'fr');
})->throws(InvalidArgumentException::class);

it('allows nested PHP translation files inside a locale directory', function (): void {
    File::ensureDirectoryExists($this->appLanguagePath . '/en/admin');
    File::put($this->appLanguagePath . '/en/admin/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'title' => 'Admin title',
];
PHP);

    SaveTranslationEntriesAction::run('app', 'php:admin/messages', 'fr', [
        'title' => 'Titre admin',
    ]);

    $values = require $this->appLanguagePath . '/fr/admin/messages.php';

    expect($values['title'])->toBe('Titre admin');
});

it('ignores invalid locale file names discovered on disk', function (): void {
    File::put($this->appLanguagePath . '/bad.locale.json', json_encode(['Unsafe' => 'Unsafe'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    $locales = collect(ListInstalledLocalesAction::run('app'))->pluck('locale')->all();

    expect($locales)->toContain('en', 'fr')
        ->and($locales)->not->toContain('bad.locale');
});

it('writes package translations to Laravel override files by default', function (): void {
    DuplicateLocaleAction::run('package:capell-app/fixture-package', 'en', 'fr');
    SaveTranslationEntriesAction::run('package:capell-app/fixture-package', 'php:package', 'fr', [
        'heading' => 'Titre de package',
    ]);

    $overridePath = lang_path('vendor/capell-fixture-package/fr/package.php');
    $sourcePath = $this->packagePath . '/resources/lang/fr/package.php';
    $values = require $overridePath;

    expect(File::exists($overridePath))->toBeTrue()
        ->and(File::exists($sourcePath))->toBeFalse()
        ->and($values['heading'])->toBe('Titre de package');
});

it('merges package override files with source files while comparing translations', function (): void {
    File::put($this->packagePath . '/resources/lang/en/package.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'heading' => 'Package heading',
    'cta' => 'Read more',
];
PHP);

    File::ensureDirectoryExists(lang_path('vendor/capell-fixture-package/fr'));
    File::put(lang_path('vendor/capell-fixture-package/fr/package.php'), <<<'PHP'
<?php

declare(strict_types=1);

return [
    'heading' => 'Titre de package',
];
PHP);

    $entries = collect(LoadTranslationComparisonAction::run('package:capell-app/fixture-package', 'php:package', 'en', 'fr'));

    expect($entries->firstWhere('key', 'heading')?->targetValue)->toBe('Titre de package')
        ->and($entries->firstWhere('key', 'cta')?->sourceValue)->toBe('Read more')
        ->and($entries->firstWhere('key', 'cta')?->status)->toBe('missing');
});

it('rejects translated strings that drop source placeholders', function (): void {
    File::put($this->appLanguagePath . '/en/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'cart' => 'You have :count items',
];
PHP);

    SaveTranslationEntriesAction::run('app', 'php:messages', 'fr', [
        'cart' => 'Vous avez des articles',
    ]);
})->throws(InvalidArgumentException::class, 'The cart translation must preserve these placeholders: :count.');

it('rejects translated strings that drop source plural forms', function (): void {
    File::put($this->appLanguagePath . '/en/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'cart' => 'One item|Many items',
];
PHP);

    SaveTranslationEntriesAction::run('app', 'php:messages', 'fr', [
        'cart' => 'Plusieurs articles',
    ]);
})->throws(InvalidArgumentException::class, 'The cart translation must preserve the same plural forms as the source string.');

it('suggests translations from exact source-value memory matches', function (): void {
    File::put($this->appLanguagePath . '/en/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'title' => 'Hello',
    'alternate' => 'Hello',
];
PHP);

    SaveTranslationEntriesAction::run('app', 'php:messages', 'fr', [
        'title' => 'Bonjour',
    ]);

    $suggestions = BuildTranslationMemorySuggestionsAction::run('app', 'en', 'fr', [
        new TranslationEntryData('alternate', 'Hello', null, 'missing', true),
    ]);

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]->key)->toBe('alternate')
        ->and($suggestions[0]->value)->toBe('Bonjour');
});

it('rejects translated strings that violate configured glossary terms', function (): void {
    config()->set('capell-translation-manager.glossary.fr', [
        'CMS' => 'SGC',
    ]);

    File::put($this->appLanguagePath . '/en/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'product' => 'Capell CMS',
];
PHP);

    SaveTranslationEntriesAction::run('app', 'php:messages', 'fr', [
        'product' => 'Gestionnaire Capell',
    ]);
})->throws(InvalidArgumentException::class, 'The product translation must use "SGC" for the glossary term "CMS".');

it('can write package source files only when package source writes are enabled', function (): void {
    config()->set('capell-translation-manager.package_source_writes', true);

    SaveTranslationEntriesAction::run('package:capell-app/fixture-package', 'php:package', 'fr', [
        'heading' => 'Titre source',
    ]);

    $sourcePath = $this->packagePath . '/resources/lang/fr/package.php';
    $overridePath = lang_path('vendor/capell-fixture-package/fr/package.php');
    $values = require $sourcePath;

    expect(File::exists($sourcePath))->toBeTrue()
        ->and(File::exists($overridePath))->toBeFalse()
        ->and($values['heading'])->toBe('Titre source');
});
