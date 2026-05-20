<?php

declare(strict_types=1);

use Capell\TranslationManager\Actions\CreateLocaleFilesAction;
use Capell\TranslationManager\Actions\DuplicateLocaleAction;
use Capell\TranslationManager\Actions\ListInstalledLocalesAction;
use Capell\TranslationManager\Actions\ListTranslationFilesAction;
use Capell\TranslationManager\Actions\ListTranslationSourcesAction;
use Capell\TranslationManager\Actions\LoadTranslationComparisonAction;
use Capell\TranslationManager\Actions\SaveTranslationEntriesAction;
use Capell\TranslationManager\Tests\Fixtures\PackageTranslationFixtureServiceProvider;
use Capell\TranslationManager\Tests\TranslationManagerTestCase;
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
    ], JSON_PRETTY_PRINT));

    File::put($this->packagePath . '/composer.json', json_encode([
        'name' => 'capell-app/fixture-package',
        'extra' => [
            'laravel' => [
                'providers' => [
                    PackageTranslationFixtureServiceProvider::class,
                ],
            ],
        ],
    ], JSON_PRETTY_PRINT));

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

    expect($packageSource->namespace)->toBe('capell-fixture-package')
        ->and($packageSource->sourceWritable)->toBeFalse();
});

it('lists locales and compares source and target entries', function (): void {
    $locales = ListInstalledLocalesAction::run('app');
    $files = ListTranslationFilesAction::run('app', 'en', 'fr');
    $entries = collect(LoadTranslationComparisonAction::run('app', 'php:messages', 'en', 'fr'));

    expect(collect($locales)->pluck('locale')->all())->toContain('en', 'fr')
        ->and(collect($files)->pluck('key')->all())->toContain('php:messages', 'json')
        ->and($entries->firstWhere('key', 'title')->status)->toBe('changed')
        ->and($entries->firstWhere('key', 'nested.body')->status)->toBe('missing')
        ->and($entries->firstWhere('key', 'nested.count')->editable)->toBeFalse();
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
    File::put($this->appLanguagePath . '/bad.locale.json', json_encode(['Unsafe' => 'Unsafe'], JSON_PRETTY_PRINT));

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
