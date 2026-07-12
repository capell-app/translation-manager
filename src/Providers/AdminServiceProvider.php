<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Providers;

use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\TranslationManager\Filament\Pages\TranslationManagerPage;
use Illuminate\Support\ServiceProvider;

final class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! CapellCore::isPackageInstalled(TranslationManagerServiceProvider::$packageName)) {
            return;
        }

        if (! class_exists(CapellAdmin::class)) {
            return;
        }

        CapellAdmin::registerExtensionPage(
            TranslationManagerServiceProvider::$packageName,
            TranslationManagerPage::class,
        );
    }
}
