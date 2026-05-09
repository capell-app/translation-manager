<?php

declare(strict_types=1);

use Capell\Admin\Support\Extensions\ExtensionPageRegistry;
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
