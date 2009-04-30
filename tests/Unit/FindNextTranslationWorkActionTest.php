<?php

declare(strict_types=1);

use Capell\TranslationManager\Actions\FindNextTranslationWorkAction;
use Capell\TranslationManager\Tests\TranslationManagerTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(TranslationManagerTestCase::class);

beforeEach(function (): void {
    $this->translationBasePath = sys_get_temp_dir() . '/capell-translation-queue/' . Str::uuid()->toString();
    $this->appLanguagePath = $this->translationBasePath . '/lang';

    File::ensureDirectoryExists($this->appLanguagePath . '/en');
    File::ensureDirectoryExists($this->appLanguagePath . '/fr');
    config()->set('capell-translation-manager.app_source.path', $this->appLanguagePath);
    config()->set('capell-translation-manager.package_paths', []);
});

afterEach(function (): void {
    File::deleteDirectory($this->translationBasePath);
});

it('prioritises missing work before stale and changed entries, then sorts by file and key', function (): void {
    File::put($this->appLanguagePath . '/en/zulu.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'changed' => 'Changed',
    'stale' => 'Stale',
    'missing' => 'Missing',
];
PHP);
    File::put($this->appLanguagePath . '/fr/zulu.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'changed' => 'Different',
    'stale' => 'Old',
    'missing' => '',
];
PHP);
    File::put($this->appLanguagePath . '/en/alpha.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'missing' => 'Missing',
];
PHP);
    File::put($this->appLanguagePath . '/fr/alpha.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'missing' => '',
];
PHP);

    $next = FindNextTranslationWorkAction::run('app', 'en', 'fr');

    expect($next)->not->toBeNull()
        ->and($next?->fileKey)->toBe('php:alpha')
        ->and($next?->key)->toBe('missing')
        ->and($next?->status)->toBe('missing');
});

it('returns no work when every editable entry is complete', function (): void {
    File::put($this->appLanguagePath . '/en/messages.php', "<?php\n\nreturn ['title' => 'Hello'];\n");
    File::put($this->appLanguagePath . '/fr/messages.php', "<?php\n\nreturn ['title' => 'Hello'];\n");

    expect(FindNextTranslationWorkAction::run('app', 'en', 'fr'))->toBeNull();
});
