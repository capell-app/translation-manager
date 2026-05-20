<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Tests;

use Capell\Admin\Providers\AdminServiceProvider;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Providers\CapellServiceProvider;
use Capell\TranslationManager\Providers\AdminServiceProvider as TranslationManagerAdminServiceProvider;
use Capell\TranslationManager\Providers\TranslationManagerServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Livewire\LivewireServiceProvider;
use Lorisleiva\Actions\ActionServiceProvider;
use Orchestra\Testbench\TestCase;
use Override;
use Spatie\LaravelData\LaravelDataServiceProvider;

class TranslationManagerTestCase extends TestCase
{
    use InteractsWithSession;

    /**
     * @return class-string[]
     */
    #[Override]
    protected function getPackageProviders(mixed $app): array
    {
        return [
            ActionServiceProvider::class,
            ActionsServiceProvider::class,
            LaravelDataServiceProvider::class,
            LivewireServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            CapellServiceProvider::class,
            AdminServiceProvider::class,
            TranslationManagerServiceProvider::class,
            TranslationManagerAdminServiceProvider::class,
        ];
    }

    #[Override]
    protected function getEnvironmentSetUp(mixed $app): void
    {
        $app->make(Repository::class)->set('app.key', 'base64:' . base64_encode('12345678901234567890123456789012'));
        $app->make(Repository::class)->set('database.default', 'testing');
        $app->make(Repository::class)->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app->make(Repository::class)->set('filament-shield', []);

        CapellCore::forcePackageInstalled(AdminServiceProvider::$packageName);
        CapellCore::forcePackageInstalled(TranslationManagerServiceProvider::$packageName);
    }
}
