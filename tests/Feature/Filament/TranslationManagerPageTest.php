<?php

declare(strict_types=1);

use Capell\Admin\Support\Extensions\ExtensionPageRegistry;
use Capell\TranslationManager\Contracts\TranslationAITranslator;
use Capell\TranslationManager\Data\AITranslationSuggestionData;
use Capell\TranslationManager\Data\TranslationEntryData;
use Capell\TranslationManager\Filament\Pages\TranslationManagerPage;
use Capell\TranslationManager\Tests\TranslationManagerTestCase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(TranslationManagerTestCase::class);

beforeEach(function (): void {
    $this->translationBasePath = sys_get_temp_dir() . '/capell-translation-manager-page/' . Str::uuid()->toString();
    $this->appLanguagePath = $this->translationBasePath . '/app-lang';

    File::ensureDirectoryExists($this->appLanguagePath . '/en');
    File::ensureDirectoryExists($this->appLanguagePath . '/fr');

    config()->set('capell-translation-manager.app_source.path', $this->appLanguagePath);
    config()->set('capell-translation-manager.package_paths', []);

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
        use HasFactory;

        public function can($abilities, $arguments = []): bool
        {
            return true;
        }
    });

    $page = resolve(TranslationManagerPage::class);
    $page->mount();

    expect(TranslationManagerPage::canAccess())->toBeTrue()
        ->and(collect($page->entries)->pluck('key')->all())->toContain('title')
        ->and(collect($page->entries)->firstWhere('key', 'title')['sourceValue'])->toBe('Hello');
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

    $page->filter = 'missing';
    $page->selectedEntryKeys = ['title'];

    expect($page->aiAvailable())->toBeTrue()
        ->and($page->localeOptions())->toHaveKeys(['en', 'fr'])
        ->and($page->filteredEntries())->toHaveCount(1)
        ->and($page->filteredEntries()[0]['key'])->toBe('title');

    $reflection = new ReflectionClass($page);
    $method = $reflection->getMethod('translateSelectedEntries');
    $method->invoke($page);

    expect($page->entries[0]['targetValue'])->toBe('en:fr:Hello');

    $page->saveTranslations();

    expect(File::getRequire($this->appLanguagePath . '/fr/messages.php'))
        ->toBe(['title' => 'en:fr:Hello']);

    $page->updatedSourceKey();

    expect($page->fileKey)->toBe('php:messages')
        ->and($page->targetLocale)->toBe('fr')
        ->and($page->entries)->not->toBeEmpty();
});
