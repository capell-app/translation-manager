<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Providers;

use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Support\Packages\RegistersInstalledRuntime;
use Capell\TranslationManager\Filament\Pages\TranslationManagerPage;
use Illuminate\Support\ServiceProvider;
use Override;

final class AdminServiceProvider extends ServiceProvider
{
    use RegistersInstalledRuntime;

    #[Override]
    public function register(): void
    {
        $this->registerInstalledRuntime(TranslationManagerServiceProvider::$packageName, 'admin');
    }

    protected function bootInstalledRuntime(): void
    {
        if (! class_exists(CapellAdmin::class)) {
            return;
        }

        CapellAdmin::registerExtensionPage(
            TranslationManagerServiceProvider::$packageName,
            TranslationManagerPage::class,
        );
    }
}
