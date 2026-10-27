<?php

declare(strict_types=1);

use Capell\Admin\Support\Extensions\ExtensionPageRegistry;
use Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider;
use Capell\AIOrchestrator\Support\Ai\PrismProvider;
use Capell\Core\Actions\InstallPackageAction;
use Capell\Core\Facades\CapellCore;
use Capell\Tests\Support\InstalledRuntimeParity;
use Capell\Tests\Support\InstalledRuntimeState;
use Capell\Tests\Support\InstalledRuntimeTestCase;
use Capell\Tests\Support\RenderedHtml;
use Capell\Tests\Support\TranslationNavigationAssertions;
use Capell\TranslationManager\Contracts\TranslationAITranslator;
use Capell\TranslationManager\Data\AITranslationSuggestionData;
use Capell\TranslationManager\Data\TranslationEntryData;
use Capell\TranslationManager\Filament\Pages\TranslationManagerPage;
use Capell\TranslationManager\Integrations\AI\PrismTranslationAITranslator;
use Capell\TranslationManager\Providers\TranslationManagerServiceProvider;
use Capell\TranslationManager\Tests\Fixtures\TranslationPrismProviderFake;
use Capell\TranslationManager\Tests\TranslationManagerTestCase;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(TranslationManagerTestCase::class);

beforeEach(function (): void {
    $this->translationBasePath = sys_get_temp_dir() . '/capell-translation-manager-page/' . Str::uuid()->toString();
    $this->appLanguagePath = $this->translationBasePath . '/app-lang';

    File::ensureDirectoryExists($this->appLanguagePath . '/en');
    File::ensureDirectoryExists($this->appLanguagePath . '/fr');
    File::ensureDirectoryExists($this->translationBasePath . '/views');

    config()->set('capell-translation-manager.app_source.path', $this->appLanguagePath);
    config()->set('capell-translation-manager.package_paths', []);
    config()->set('capell-translation-manager.scan_paths', [$this->translationBasePath . '/views']);

    File::put($this->appLanguagePath . '/en/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'title' => 'Hello',
];
PHP);

    File::put($this->appLanguagePath . '/fr/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'title' => '',
];
PHP);

});

afterEach(function (): void {
    File::deleteDirectory($this->translationBasePath);
});

it('registers the translation manager as an extension page', function (): void {
    $extensionPages = collect(resolve(ExtensionPageRegistry::class)->entries())
        ->pluck('page');

    expect($extensionPages)->toContain(TranslationManagerPage::class);
});

it('keeps AI translation visible but disabled with an installation explanation when unavailable', function (): void {
    $page = resolve(TranslationManagerPage::class);
    $translateAction = translationManagerHeaderAction($page, 'translateSelected');

    expect($translateAction->isVisible())->toBeTrue()
        ->and($translateAction->isDisabled())->toBeTrue()
        ->and($translateAction->getTooltip())->toBe(
            __('capell-translation-manager::package.ai_unavailable'),
        );
});

it('renders translation entries for admins who can manage extensions', function (): void {
    $this->actingAs(new class extends AuthenticatableUser
    {
        /** @use HasFactory<Factory<static>> */
        use HasFactory;

        /**
         * @param  iterable<int, mixed>  $abilities
         */
        #[Override]
        public function can($abilities, $arguments = []): bool
        {
            return true;
        }
    });

    $page = resolve(TranslationManagerPage::class);
    $page->mount();
    $page->sourceKey = 'app';
    $page->refreshBrowser();

    $titleEntry = collect($page->entries)->firstWhere('key', 'title');

    throw_if($titleEntry === null, RuntimeException::class, 'Expected title translation entry to exist.');

    expect(TranslationManagerPage::canAccess())->toBeTrue()
        ->and(collect($page->entries)->pluck('key')->all())->toContain('title')
        ->and($titleEntry['sourceValue'])->toBe('Hello');
});

it('filters saves and translates entries from the page state', function (): void {
    app()->instance(TranslationAITranslator::class, new class implements TranslationAITranslator
    {
        public function available(): bool
        {
            return true;
        }

        /**
         * @param  array<int, TranslationEntryData>  $entries
         * @return array<int, AITranslationSuggestionData>
         */
        public function translateSelected(string $sourceLocale, string $targetLocale, array $entries): array
        {
            return collect($entries)
                ->map(static fn (TranslationEntryData $entry): AITranslationSuggestionData => new AITranslationSuggestionData(
                    key: $entry->key,
                    value: sprintf('%s:%s:%s', $sourceLocale, $targetLocale, $entry->sourceValue),
                ))
                ->all();
        }
    });

    $page = resolve(TranslationManagerPage::class);
    $page->mount();
    $page->sourceKey = 'app';
    $page->refreshBrowser();

    $page->filter = 'missing';
    $page->selectedEntryKeys = ['title'];

    expect($page->aiAvailable())->toBeTrue()
        ->and($page->localeOptions())->toHaveKeys(['en', 'fr'])
        ->and($page->filteredEntries())->toHaveCount(1)
        ->and($page->filteredEntries()[0]['key'])->toBe('title');

    translationManagerRunAction(translationManagerHeaderAction($page, 'translateSelected'));

    expect($page->entries[0]['targetValue'])->toBe('')
        ->and($page->pendingAiSuggestions)->toBe(['title' => 'en:fr:Hello']);

    $page->acceptAiSuggestion('title');

    expect($page->entries[0]['targetValue'])->toBe('en:fr:Hello')
        ->and($page->pendingAiSuggestions)->toBe([]);

    $page->saveTranslations();

    expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))
        ->toBe(['title' => 'en:fr:Hello']);

    $page->updatedSourceKey();

    expect($page->fileKey)->toBe('php:messages')
        ->and($page->targetLocale)->toBe('fr')
        ->and($page->entries)->not->toBeEmpty();
});

it('switches to the Prism translator and persists an accepted translation when AI Orchestrator is available', function (): void {
    InstalledRuntimeState::run(function (): void {
        $app = InstalledRuntimeTestCase::boot(InstalledRuntimeParity::manifest('translation-manager'), false, function (Application $app): void {
            $app->make(Repository::class)->set('capell-translation-manager.app_source.path', $this->appLanguagePath);
            $app->make(Repository::class)->set('capell-translation-manager.package_paths', []);
            $app->make(Repository::class)->set('capell-translation-manager.scan_paths', [$this->translationBasePath . '/views']);
        }, sourceRoot: dirname(__DIR__, 3));
        expect(resolve(TranslationAITranslator::class))->not->toBeInstanceOf(PrismTranslationAITranslator::class);

        app()->register(AIOrchestratorServiceProvider::class);
        CapellCore::forcePackageInstalled(AIOrchestratorServiceProvider::$packageName);

        $provider = new TranslationPrismProviderFake('{"translations":{"title":"Bonjour"}}');
        app()->instance(PrismProvider::class, $provider);

        InstalledRuntimeTestCase::prepareLateInstallMigrations($app);
        InstallPackageAction::run(CapellCore::getPackage(TranslationManagerServiceProvider::$packageName));

        $translator = resolve(TranslationAITranslator::class);

        expect(CapellCore::isPackageAvailable(AIOrchestratorServiceProvider::$packageName))->toBeTrue()
            ->and($translator::class)->toBe(PrismTranslationAITranslator::class);

        $page = resolve(TranslationManagerPage::class);
        $page->mount();
        $page->sourceKey = 'app';
        $page->refreshBrowser();
        $page->selectedEntryKeys = ['title'];

        $translateAction = translationManagerHeaderAction($page, 'translateSelected');

        expect($translateAction->isVisible())->toBeTrue()
            ->and($translateAction->isDisabled())->toBeFalse();

        translationManagerRunAction($translateAction);

        expect($page->pendingAiSuggestions)->toBe(['title' => 'Bonjour'])
            ->and($provider->requests)->toHaveCount(1)
            ->and(data_get($provider->requests, '0.messages.1.content'))->toContain('"target_locale":"fr"');

        $page->acceptAiSuggestion('title');
        $page->saveTranslations();

        expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))
            ->toBe(['title' => 'Bonjour']);
    });
});

it('only saves server-authorized editable translation keys from Livewire state', function (): void {
    $page = resolve(TranslationManagerPage::class);
    $page->mount();
    $page->sourceKey = 'app';
    $page->refreshBrowser();

    $page->entries = [
        [
            'index' => 0,
            'key' => 'title',
            'sourceValue' => 'Hello',
            'targetValue' => 'Bonjour',
            'status' => 'missing',
            'editable' => false,
        ],
        [
            'index' => 1,
            'key' => 'admin.injected',
            'sourceValue' => 'Injected',
            'targetValue' => 'Injected',
            'status' => 'missing',
            'editable' => true,
        ],
    ];

    $page->saveTranslations();

    expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))
        ->toBe(['title' => 'Bonjour']);
});

it('drives locale creation duplication translation and save header actions from the page', function (): void {
    app()->instance(TranslationAITranslator::class, new class implements TranslationAITranslator
    {
        public function available(): bool
        {
            return true;
        }

        /**
         * @param  array<int, TranslationEntryData>  $entries
         * @return array<int, AITranslationSuggestionData>
         */
        public function translateSelected(string $sourceLocale, string $targetLocale, array $entries): array
        {
            return collect($entries)
                ->map(static fn (TranslationEntryData $entry): AITranslationSuggestionData => new AITranslationSuggestionData(
                    key: $entry->key,
                    value: sprintf('header:%s:%s:%s', $sourceLocale, $targetLocale, $entry->sourceValue),
                ))
                ->all();
        }
    });

    $page = resolve(TranslationManagerPage::class);
    $page->mount();
    $page->sourceKey = 'app';
    $page->refreshBrowser();

    $actions = collect(translationManagerHeaderActions($page))
        ->filter(fn (mixed $action): bool => $action instanceof Action)
        ->each(fn (Action $action): Action => $action->livewire($page))
        ->keyBy(fn (Action $action): string => (string) $action->getName());

    expect($actions->keys()->all())->toBe([
        'createLocale',
        'duplicateLocale',
        'translateSelected',
        'exportCsv',
        'exportXliff',
        'exportPo',
        'importTranslations',
        'publishReadiness',
        'scanMissingKeys',
        'saveTranslations',
    ]);

    translationManagerRunAction($actions->get('createLocale'), ['locale' => 'es']);

    expect($page->targetLocale)->toBe('es')
        ->and(File::exists($this->appLanguagePath . '/es/messages.php'))->toBeTrue();

    translationManagerRunAction($actions->get('duplicateLocale'), [
        'from_locale' => 'en',
        'target_locale' => 'de',
    ]);

    expect($page->targetLocale)->toBe('de')
        ->and(File::getRequire($this->appLanguagePath . '/de/messages.php'))->toBe(['title' => 'Hello']);

    $page->selectedEntryKeys = ['title'];
    translationManagerRunAction($actions->get('translateSelected'));

    expect($page->entries[0]['targetValue'])->toBe('Hello')
        ->and($page->pendingAiSuggestions)->toBe(['title' => 'header:en:de:Hello']);

    $page->acceptAiSuggestion('title');
    translationManagerRunAction($actions->get('saveTranslations'));

    expect($page->entries[0]['targetValue'])->toBe('header:en:de:Hello')
        ->and(File::getRequire($this->appLanguagePath . '/de/messages.php'))->toBe(['title' => 'header:en:de:Hello']);

    $exportResponse = translationManagerRunAction($actions->get('exportCsv'));

    expect($exportResponse)->toBeInstanceOf(StreamedResponse::class);

    translationManagerRunAction($actions->get('importTranslations'), [
        'format' => 'csv',
        'contents' => "key,target_value\ntitle,Importe\n",
    ]);
    translationManagerRunAction($actions->get('publishReadiness'));

    File::put($this->translationBasePath . '/views/missing.blade.php', "{{ __('messages.not_found') }}");
    translationManagerRunAction($actions->get('scanMissingKeys'));

    expect(File::getRequire($this->appLanguagePath . '/de/messages.php'))
        ->toBe(['title' => 'Importe'])
        ->and($page->missingCodeKeys[0]['key'])->toBe('messages.not_found');
});

it('persists selected source locale file and filter between page visits', function (): void {
    session()->put('capell.translation-manager.selection', [
        'sourceKey' => 'app',
        'sourceLocale' => 'en',
        'targetLocale' => 'fr',
        'fileKey' => 'php:messages',
        'filter' => 'needs_attention',
    ]);

    $page = resolve(TranslationManagerPage::class);
    $page->mount();

    expect($page->sourceKey)->toBe('app')
        ->and($page->sourceLocale)->toBe('en')
        ->and($page->targetLocale)->toBe('fr')
        ->and($page->fileKey)->toBe('php:messages')
        ->and($page->filter)->toBe('needs_attention')
        ->and($page->filteredEntries())->toHaveCount(1)
        ->and($page->readinessMatrix)->not->toBeEmpty();
});

it('rejects pending AI suggestions without changing target values', function (): void {
    $page = resolve(TranslationManagerPage::class);
    $page->mount();
    $page->sourceKey = 'app';
    $page->refreshBrowser();
    $page->pendingAiSuggestions = ['title' => 'Suggestion'];

    $page->rejectAiSuggestion('title');

    expect($page->pendingAiSuggestions)->toBe([])
        ->and($page->entries[0]['targetValue'])->toBe('');
});

it('continues to the first actionable file and key in deterministic priority order', function (): void {
    File::put($this->appLanguagePath . '/en/alpha.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'first' => 'First',
];
PHP);
    File::put($this->appLanguagePath . '/fr/alpha.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'first' => '',
];
PHP);
    File::put($this->appLanguagePath . '/en/zulu.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'last' => 'Last',
];
PHP);
    File::put($this->appLanguagePath . '/fr/zulu.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'last' => '',
];
PHP);

    /** @var TranslationManagerPage $page */
    $page = resolve(TranslationManagerPage::class);
    $page->mount();
    $page->sourceKey = 'app';
    $page->refreshBrowser();
    $page->continueTranslating();

    expect($page->fileKey)->toBe('php:alpha')
        ->and($page->focusedEntryKey)->toBe('first')
        ->and($page->filter)->toBe('needs_attention');
});

describe('Livewire translation journey', function (): void {
    beforeEach(function (): void {
        $this->actingAs(new class extends AuthenticatableUser
        {
            /** @use HasFactory<Factory<static>> */
            use HasFactory;

            /** @param iterable<int, mixed> $abilities */
            #[Override]
            public function can($abilities, $arguments = []): bool
            {
                return true;
            }
        });

        $panel = Panel::make()->id('admin')->path('admin')->default();
        Filament::registerPanel($panel);
        Filament::setCurrentPanel($panel);
    });

    it('guards import decisions without losing edits or writing on cancellation', function (string $decision): void {
        File::put($this->appLanguagePath . '/en/messages.php', "<?php return ['title' => 'Hello', 'body' => 'Body'];");
        File::put($this->appLanguagePath . '/fr/messages.php', "<?php return ['title' => '', 'body' => ''];");
        $component = Livewire::test(TranslationManagerPage::class)->set('sourceKey', 'app');
        $entries = $component->get('entries');
        throw_unless(is_array($entries), RuntimeException::class, 'Expected translation entries.');
        $titleIndex = array_search('title', array_column($entries, 'key'), true);
        throw_unless(is_int($titleIndex), RuntimeException::class, 'Expected the title entry.');
        $component->set("entries.{$titleIndex}.targetValue", 'Bonjour')
            ->callAction('importTranslations', data: ['format' => 'csv', 'contents' => "key,target_value\nbody,Corps"])
            ->assertSet("entries.{$titleIndex}.targetValue", 'Bonjour')
            ->assertSet('unsavedEntryCount', 1);
        expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))->toBe(['title' => '', 'body' => '']);
        $component->call($decision);
        if ($decision === 'stayOnCurrentFile') {
            $component->assertSet("entries.{$titleIndex}.targetValue", 'Bonjour')->assertSet('unsavedEntryCount', 1);
            expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))->toBe(['title' => '', 'body' => '']);
        } else {
            expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))->toBe([
                'title' => $decision === 'saveAndContinue' ? 'Bonjour' : '', 'body' => 'Corps',
            ]);
            $component->assertSet('unsavedEntryCount', 0)->assertSet('pendingNavigation', null);
        }
    })->with(['stayOnCurrentFile', 'saveAndContinue', 'discardChangesAndContinue']);

    it('preserves edited state when an import fails after choosing discard', function (): void {
        Livewire::test(TranslationManagerPage::class)->set('sourceKey', 'app')
            ->set('entries.0.targetValue', 'Bonjour')
            ->callAction('importTranslations', data: ['format' => 'csv', 'contents' => 'malformed'])
            ->call('discardChangesAndContinue')
            ->assertSet('entries.0.targetValue', 'Bonjour')->assertSet('unsavedEntryCount', 1);
        expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))->toBe(['title' => '']);
    });

    it('keeps import pending when saving invalid edits fails', function (): void {
        File::put($this->appLanguagePath . '/en/messages.php', "<?php return ['title' => 'Hello :name'];");
        Livewire::test(TranslationManagerPage::class)->set('sourceKey', 'app')
            ->set('entries.0.targetValue', 'Bonjour')
            ->callAction('importTranslations', data: ['format' => 'csv', 'contents' => "key,target_value\ntitle,Salut :name"])
            ->call('saveAndContinue')->assertSet('entries.0.targetValue', 'Bonjour')
            ->assertSet('unsavedEntryCount', 1)->assertNotSet('pendingNavigation', null);
        expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))->toBe(['title' => '']);
    });

    it('guards the reload after creating or duplicating a locale', function (string $action, array $data): void {
        Livewire::test(TranslationManagerPage::class)->set('sourceKey', 'app')
            ->set('entries.0.targetValue', 'Bonjour')->callAction($action, data: $data)
            ->assertSet('targetLocale', 'fr')->assertSet('entries.0.targetValue', 'Bonjour')
            ->assertSet('unsavedEntryCount', 1)->call('saveAndContinue')->assertSet('targetLocale', 'de');
        expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))->toBe(['title' => 'Bonjour']);
    })->with([['createLocale', ['locale' => 'de']], ['duplicateLocale', ['from_locale' => 'en', 'target_locale' => 'de']]]);

    it('defers same-locale writes until the dirty-edit decision', function (string $action, string $decision): void {
        File::put($this->appLanguagePath . '/fr/messages.php', "<?php return ['title' => 'Avant'];");
        $data = $action === 'createLocale' ? ['locale' => 'fr'] : ['from_locale' => 'en', 'target_locale' => 'fr'];
        $component = Livewire::test(TranslationManagerPage::class)->set('sourceKey', 'app')
            ->set('entries.0.targetValue', 'Bonjour')->callAction($action, data: $data)
            ->assertSet('entries.0.targetValue', 'Bonjour')->assertSet('unsavedEntryCount', 1)
            ->assertNotSet('pendingNavigation', null);
        expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))->toBe(['title' => 'Avant']);

        $component->call($decision)->assertSet('pendingNavigation', null);
        if ($decision === 'stayOnCurrentFile') {
            $component->assertSet('entries.0.targetValue', 'Bonjour')->assertSet('unsavedEntryCount', 1);
            expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))->toBe(['title' => 'Avant']);
        } else {
            // Continuing explicitly runs the requested blank/copy operation, including its overwrite semantics.
            $expected = $action === 'createLocale' ? '' : 'Hello';
            $component->assertSet('entries.0.targetValue', $expected)->assertSet('unsavedEntryCount', 0);
            expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))->toBe(['title' => $expected]);
        }
    })->with(['createLocale', 'duplicateLocale'])->with(['stayOnCurrentFile', 'saveAndContinue', 'discardChangesAndContinue']);

    it('refreshes rendered locale options only after an authorised locale write', function (string $action, string $decision): void {
        $data = $action === 'createLocale' ? ['locale' => 'de'] : ['from_locale' => 'fr', 'target_locale' => 'de'];
        $component = Livewire::test(TranslationManagerPage::class)->set('sourceKey', 'app');
        if ($decision !== 'clean') {
            $component->set('entries.0.targetValue', 'Bonjour');
        }

        $component->callAction($action, data: $data);
        if ($decision !== 'clean') {
            expect(File::exists($this->appLanguagePath . '/de/messages.php'))->toBeFalse();
            $component->call($decision);
        }

        $html = new RenderedHtml($component->html());
        if ($decision === 'stayOnCurrentFile') {
            expect($html->count('//select/option[@value="de"]'))->toBe(0)
                ->and(File::exists($this->appLanguagePath . '/de/messages.php'))->toBeFalse();
            $component->assertSet('entries.0.targetValue', 'Bonjour');
        } else {
            expect($html->count('//select/option[@value="de"]'))->toBeGreaterThan(0);
            $expectedSource = $decision === 'saveAndContinue' ? 'Bonjour' : '';
            $expectedTarget = $action === 'createLocale' ? '' : $expectedSource;
            $component->assertSet('targetLocale', 'de')->assertSet('entries.0.targetValue', $expectedTarget);
            expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))->toBe(['title' => $expectedSource])
                ->and(File::getRequire($this->appLanguagePath . '/de/messages.php'))->toBe(['title' => $expectedTarget]);
        }
    })->with(['createLocale', 'duplicateLocale'])->with(['clean', 'stayOnCurrentFile', 'saveAndContinue', 'discardChangesAndContinue']);

    it('keeps a locale operation pending when saving invalid edits fails', function (string $action): void {
        File::put($this->appLanguagePath . '/en/messages.php', "<?php return ['title' => 'Hello :name'];");
        $data = $action === 'createLocale' ? ['locale' => 'de'] : ['from_locale' => 'en', 'target_locale' => 'de'];
        Livewire::test(TranslationManagerPage::class)->set('sourceKey', 'app')
            ->set('entries.0.targetValue', 'Bonjour')->callAction($action, data: $data)
            ->call('saveAndContinue')->assertSet('entries.0.targetValue', 'Bonjour')
            ->assertSet('unsavedEntryCount', 1)->assertNotSet('pendingNavigation', null);
        expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))->toBe(['title' => ''])
            ->and(File::exists($this->appLanguagePath . '/de/messages.php'))->toBeFalse();
    })->with(['createLocale', 'duplicateLocale']);

    it('preserves edited entries if a deferred locale operation cannot write', function (string $action): void {
        $data = $action === 'createLocale' ? ['locale' => '../outside'] : ['from_locale' => 'en', 'target_locale' => '../outside'];
        Livewire::test(TranslationManagerPage::class)->set('sourceKey', 'app')
            ->set('entries.0.targetValue', 'Bonjour')->callAction($action, data: $data)
            ->call('discardChangesAndContinue')->assertSet('entries.0.targetValue', 'Bonjour')
            ->assertSet('unsavedEntryCount', 1)->assertNotSet('pendingNavigation', null);
        expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))->toBe(['title' => '']);
    })->with(['createLocale', 'duplicateLocale']);

    it('locks the deferred locale operation against forged destinations', function (string $property): void {
        $component = Livewire::test(TranslationManagerPage::class)->set('sourceKey', 'app')
            ->set('entries.0.targetValue', 'Bonjour')->callAction('createLocale', data: ['locale' => 'de']);
        expect(fn () => $component->set($property, 'it'))->toThrow(CannotUpdateLockedPropertyException::class);
        expect(File::exists($this->appLanguagePath . '/de/messages.php'))->toBeFalse()
            ->and(File::exists($this->appLanguagePath . '/it/messages.php'))->toBeFalse();
    })->with(['pendingLocaleOperation.targetLocale', 'pendingLocaleOperation.fromLocale']);

    it('guards reload and preserves edits until an explicit discard', function (): void {
        Livewire::test(TranslationManagerPage::class)->set('sourceKey', 'app')
            ->set('entries.0.targetValue', 'Bonjour')->call('refreshBrowser')
            ->assertSet('entries.0.targetValue', 'Bonjour')->assertSet('unsavedEntryCount', 1)
            ->call('stayOnCurrentFile')->assertSet('entries.0.targetValue', 'Bonjour')
            ->call('refreshBrowser')->call('discardChangesAndContinue')
            ->assertSet('entries.0.targetValue', '')->assertSet('unsavedEntryCount', 0);
    });

    it('names each editable row by key and target locale through Livewire updates', function (string $locale): void {
        File::ensureDirectoryExists($this->appLanguagePath . '/' . $locale);
        File::put($this->appLanguagePath . '/en/messages.php', "<?php return ['title' => 'Hello', 'body' => 'Body', 'footer' => 'Footer'];");
        File::put($this->appLanguagePath . '/' . $locale . '/messages.php', "<?php return ['title' => '', 'body' => '', 'footer' => ''];");
        $component = Livewire::test(TranslationManagerPage::class)->set('sourceKey', 'app')->set('targetLocale', $locale);
        $selected = [];
        $ids = [];
        foreach (['title', 'body', 'footer'] as $key) {
            $html = new RenderedHtml($component->html());
            foreach (['textarea' => 'edit_entry', 'input[@type="checkbox"]' => 'select_entry'] as $control => $label) {
                $name = __('capell-translation-manager::package.' . $label, ['key' => $key, 'locale' => $locale]);
                $element = $html->element('//' . $control . '[@aria-label="' . $name . '"]');
                expect($element->getAttribute('aria-label'))->toContain($key)->toContain($locale);
                $ids[] = $element->getAttribute('id');
                if ($control === 'input[@type="checkbox"]' && $key !== 'footer') {
                    $selected[] = $element->getAttribute('value');
                }
            }
        }

        expect(array_unique($ids))->toHaveCount(6)->not->toContain('');
        app()->instance(TranslationAITranslator::class, new class implements TranslationAITranslator
        {
            #[Override]
            public function available(): bool
            {
                return true;
            }

            /**
             * @param  array<int, TranslationEntryData>  $entries
             * @return array<int, AITranslationSuggestionData>
             */
            #[Override]
            public function translateSelected(string $sourceLocale, string $targetLocale, array $entries): array
            {
                return array_map(static fn (TranslationEntryData $entry): AITranslationSuggestionData => new AITranslationSuggestionData($entry->key, 'Draft ' . $entry->key), $entries);
            }
        });
        $component->set('selectedEntryKeys', $selected)->callAction('translateSelected')
            ->assertSet('pendingAiSuggestions', ['title' => 'Draft title', 'body' => 'Draft body'])
            ->call('continueTranslating')->assertDispatched('translation-manager-focus-entry');
        $html = new RenderedHtml($component->html());
        foreach ($ids as $id) {
            expect($html->count('//*[@id="' . $id . '"]'))->toBe(1);
        }
    })->with(['fr', 'de']);

    it('renders a target-locale queue with one primary continuation action and labelled secondary disclosure', function (): void {
        $component = Livewire::test(TranslationManagerPage::class)->set('sourceKey', 'app');
        $html = new RenderedHtml($component->html());
        TranslationNavigationAssertions::assertGuards($component->html());

        foreach (['translation_work_queue', 'translate_into', 'continue_translating', 'advanced_filters_and_tools', 'locale_tools', 'file_tools', 'ai_tools'] as $label) {
            $component->assertSee(__('capell-translation-manager::package.' . $label));
        }

        $continuationButton = '//button[normalize-space(.)="' . __('capell-translation-manager::package.continue_translating') . '"]';
        expect($html->count($continuationButton))->toBe(1);
        expect($html->count('//details/summary'))->toBe(1);
        expect($html->element('//details/summary')->textContent)
            ->toContain(__('capell-translation-manager::package.advanced_filters_and_tools'));
        expect($html->count('//label[.//span[normalize-space(.)="' . __('capell-translation-manager::package.translate_into') . '"]]//select'))->toBe(1);

        $component->call($html->element($continuationButton)->getAttribute('wire:click'))
            ->assertSee('Hello')
            ->assertSee(__('capell-translation-manager::package.save_file'));
        $component->call('mountAction', 'createLocale')->assertActionMounted('createLocale');
    });

    it('reveals the continued key in rendered Livewire output and requests focus every time', function (): void {
        Livewire::test(TranslationManagerPage::class)
            ->set('sourceKey', 'app')
            ->set('filter', 'same')
            ->call('continueTranslating')
            ->assertSet('focusedEntryKey', 'title')
            ->assertSeeHtml('data-focused-entry="title"')
            ->assertDispatched('translation-manager-focus-entry', id: 'translation-entry-' . hash('xxh128', 'title'))
            ->call('continueTranslating')
            ->assertDispatched('translation-manager-focus-entry', id: 'translation-entry-' . hash('xxh128', 'title'));
    });

    it('keeps the navigation guard dirty after an unsaved Livewire edit and clears it after saving', function (): void {
        Livewire::test(TranslationManagerPage::class)
            ->set('sourceKey', 'app')
            ->set('entries.0.targetValue', 'Bonjour')
            ->assertSet('unsavedEntryCount', 1)
            ->assertSeeHtml('data-unsaved-entry-count="1"')
            ->assertSee(__('capell-translation-manager::package.unsaved_changes_hint'))
            ->call('saveTranslations')
            ->assertSet('unsavedEntryCount', 0)
            ->assertSeeHtml('data-unsaved-entry-count="0"');
    });

    it('preserves another editors translation when saving a different key from stale page state', function (): void {
        File::put($this->appLanguagePath . '/en/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'title' => 'Title',
    'body' => 'Body',
];
PHP);
        File::put($this->appLanguagePath . '/fr/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'title' => 'A',
    'body' => 'B',
];
PHP);

        $firstEditor = Livewire::test(TranslationManagerPage::class)->set('sourceKey', 'app');
        $secondEditor = Livewire::test(TranslationManagerPage::class)->set('sourceKey', 'app');
        $entries = $firstEditor->get('entries');

        throw_unless(is_array($entries), RuntimeException::class, 'Expected the translation page to expose editable entries.');

        $titleIndex = collect($entries)->search(fn (array $entry): bool => $entry['key'] === 'title');
        $bodyIndex = collect($entries)->search(fn (array $entry): bool => $entry['key'] === 'body');

        throw_unless(is_int($titleIndex), RuntimeException::class, 'Expected a title translation entry.');
        throw_unless(is_int($bodyIndex), RuntimeException::class, 'Expected a body translation entry.');

        $firstEditor
            ->set("entries.{$titleIndex}.targetValue", 'A2')
            ->call('saveTranslations');

        $secondEditor
            ->set("entries.{$bodyIndex}.targetValue", 'B2')
            ->call('saveTranslations');

        expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))
            ->toBe([
                'title' => 'A2',
                'body' => 'B2',
            ]);
    });
});

it('shows save, discard and stay decisions for dirty locale and file navigation', function (): void {
    /** @var TranslationManagerPage $page */
    $page = resolve(TranslationManagerPage::class);
    $page->mount();
    $page->sourceKey = 'app';
    $page->refreshBrowser();
    $page->entries[0]['targetValue'] = 'Bonjour';
    $page->updatedEntries();

    expect($page->dirtyEntryCount())->toBe(1)
        ->and($page->hasUnsavedChanges())->toBeTrue();

    $page->targetLocale = 'en';
    $page->updatedTargetLocale();

    expect($page->targetLocale)->toBe('fr')
        ->and($page->pendingNavigation)->toMatchArray([
            'property' => 'targetLocale',
            'value' => 'en',
        ]);

    $page->stayOnCurrentFile();

    expect($page->pendingNavigation)->toBeNull()
        ->and($page->dirtyEntryCount())->toBe(1);

    $page->targetLocale = 'en';
    $page->updatedTargetLocale();
    $page->discardChangesAndContinue();

    expect($page->targetLocale)->toBe('en')
        ->and($page->dirtyEntryCount())->toBe(0);
});

it('keeps dirty values when saving the current file fails', function (): void {
    File::put($this->appLanguagePath . '/en/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'cart' => 'You have :count items',
];
PHP);
    File::put($this->appLanguagePath . '/fr/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'cart' => '',
];
PHP);

    /** @var TranslationManagerPage $page */
    $page = resolve(TranslationManagerPage::class);
    $page->mount();
    $page->sourceKey = 'app';
    $page->refreshBrowser();
    $page->entries[0]['targetValue'] = 'Vous avez des articles';
    $page->updatedEntries();

    expect([$page->sourceKey, $page->fileKey, $page->targetLocale])->toBe(['app', 'php:messages', 'fr']);

    expect($page->saveTranslations())->toBeFalse()
        ->and($page->entries[0]['targetValue'])->toBe('Vous avez des articles')
        ->and($page->dirtyEntryCount())->toBe(1)
        ->and(File::getRequire($this->appLanguagePath . '/fr/messages.php'))
        ->toBe(['cart' => '']);
});

/**
 * @return array<int, Action>
 */
function translationManagerHeaderActions(TranslationManagerPage $page): array
{
    if ($page->getCachedHeaderActions() === []) {
        $page->cacheInteractsWithHeaderActions();
    }

    $actions = $page->getCachedHeaderActions();
    $validated = [];
    foreach ($actions as $action) {
        throw_unless($action instanceof Action, RuntimeException::class, 'Each header action must be a Filament action.');
        $validated[] = $action;
    }

    return $validated;
}

function translationManagerHeaderAction(TranslationManagerPage $page, string $name): Action
{
    $action = collect(translationManagerHeaderActions($page))
        ->filter(fn (mixed $action): bool => $action instanceof Action)
        ->each(fn (Action $action): Action => $action->livewire($page))
        ->first(fn (Action $action): bool => $action->getName() === $name);

    if (! $action instanceof Action) {
        throw new RuntimeException(sprintf('Expected the %s header action.', $name));
    }

    return $action;
}

/**
 * @param  array<string, mixed>  $data
 */
function translationManagerRunAction(?Action $action, array $data = []): mixed
{
    if (! $action instanceof Action) {
        throw new RuntimeException('Expected a translation manager header action.');
    }

    $closure = $action->getActionFunction();

    expect($closure)->not->toBeNull();

    return $action->evaluate($closure, ['data' => $data], [Action::class => $action]);
}

it('loads populated screenshot comparison rows from the real file store', function (): void {
    config()->set('capell-translation-manager.app_source.path', dirname(__DIR__, 5) . '/workbench/resources/screenshots/translations');
    config()->set('capell-translation-manager.app_source.writable', false);

    session()->put('capell.translation-manager.selection', [
        'sourceKey' => 'app', 'sourceLocale' => 'en', 'targetLocale' => 'fr', 'fileKey' => 'json', 'filter' => 'all',
    ]);
    $page = resolve(TranslationManagerPage::class);
    $page->mount();

    expect($page->entries)->toHaveCount(4);
    $translated = collect($page->entries)->firstWhere('key', 'Book a workshop');
    $missing = collect($page->entries)->firstWhere('key', 'Plan your visit');
    $this->assertNotNull($translated);
    $this->assertNotNull($missing);
    expect($translated['targetValue'])->toBe('Réserver un atelier')
        ->and($missing['status'])->toBe('missing');
});
