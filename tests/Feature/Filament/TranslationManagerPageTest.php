<?php

declare(strict_types=1);

use Capell\Admin\Support\Extensions\ExtensionPageRegistry;
use Capell\TranslationManager\Contracts\TranslationAITranslator;
use Capell\TranslationManager\Data\AITranslationSuggestionData;
use Capell\TranslationManager\Data\TranslationEntryData;
use Capell\TranslationManager\Filament\Pages\TranslationManagerPage;
use Capell\TranslationManager\Tests\TranslationManagerTestCase;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
        ->keyBy(fn (Action $action): string => $action->getName());

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

/**
 * @return array<int, Action>
 */
function translationManagerHeaderActions(TranslationManagerPage $page): array
{
    $method = new ReflectionMethod(TranslationManagerPage::class, 'getHeaderActions');

    return $method->invoke($page);
}

/**
 * @param  array<string, mixed>  $data
 */
function translationManagerRunAction(?Action $action, array $data = []): mixed
{
    expect($action)->toBeInstanceOf(Action::class);

    $closure = $action->getActionFunction();

    expect($closure)->not->toBeNull();

    return $action->evaluate($closure, ['data' => $data], [Action::class => $action]);
}
