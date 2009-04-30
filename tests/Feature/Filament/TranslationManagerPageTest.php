<?php

declare(strict_types=1);

use Capell\Admin\Support\Extensions\ExtensionPageRegistry;
use Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider;
use Capell\AIOrchestrator\Support\Ai\PrismProvider;
use Capell\Core\Facades\CapellCore;
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
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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

    expect($translateAction)->toBeInstanceOf(Action::class)
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

    $reflection = new ReflectionClass($page);
    $method = $reflection->getMethod('translateSelectedEntries');
    $method->invoke($page);

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
    expect(resolve(TranslationAITranslator::class))->not->toBeInstanceOf(PrismTranslationAITranslator::class);

    app()->register(AIOrchestratorServiceProvider::class);
    CapellCore::forcePackageInstalled(AIOrchestratorServiceProvider::$packageName);

    $provider = new TranslationPrismProviderFake('{"translations":{"title":"Bonjour"}}');
    app()->instance(PrismProvider::class, $provider);

    new TranslationManagerServiceProvider(app())->registeringPackage();

    expect(CapellCore::isPackageAvailable(AIOrchestratorServiceProvider::$packageName))->toBeTrue()
        ->and(resolve(TranslationAITranslator::class))->toBeInstanceOf(PrismTranslationAITranslator::class);

    $page = resolve(TranslationManagerPage::class);
    $page->mount();
    $page->sourceKey = 'app';
    $page->refreshBrowser();
    $page->selectedEntryKeys = ['title'];

    $translateAction = translationManagerHeaderAction($page, 'translateSelected');

    expect($translateAction)->toBeInstanceOf(Action::class)
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

    foreach ($actions as $action) {
        $actionSchema = $action->getSchema(Schema::make($page));

        if ($actionSchema instanceof Schema) {
            expect($actionSchema->getComponents())->toBeArray();
        }
    }

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

it('renders a target-locale queue with one primary continuation action and labelled secondary disclosure', function (): void {
    $view = File::get(__DIR__ . '/../../../resources/views/filament/pages/translation-manager.blade.php');
    $pageSource = File::get(__DIR__ . '/../../../src/Filament/Pages/TranslationManagerPage.php');

    expect($view)
        ->toContain('translation_work_queue')
        ->toContain('translate_into')
        ->toContain('continue_translating')
        ->toContain('advanced_filters_and_tools')
        ->toContain('locale_tools')
        ->toContain('file_tools')
        ->toContain('ai_tools')
        ->toContain('<details')
        ->toContain('beforeUnload(event)')
        ->toContain("mountAction('createLocale')");

    expect($pageSource)->toContain("__('capell-translation-manager::package.save_file')");
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
            public function can($abilities, $arguments = []): bool
            {
                return true;
            }
        });

        $panel = Panel::make()->id('admin')->path('admin')->default();
        Filament::registerPanel($panel);
        Filament::setCurrentPanel($panel);
    });

    it('reveals the continued key in rendered Livewire output and requests focus every time', function (): void {
        Livewire::test(TranslationManagerPage::class)
            ->set('sourceKey', 'app')
            ->set('filter', 'same')
            ->call('continueTranslating')
            ->assertSet('focusedEntryKey', 'title')
            ->assertSeeHtml('data-focused-entry="title"')
            ->assertDispatched('translation-manager-focus-entry', id: 'translation-entry-' . md5('title'))
            ->call('continueTranslating')
            ->assertDispatched('translation-manager-focus-entry', id: 'translation-entry-' . md5('title'));
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
    $method = new ReflectionMethod(TranslationManagerPage::class, 'getHeaderActions');

    return $method->invoke($page);
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
