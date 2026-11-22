<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Providers;

use Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Database\RuntimeSchemaState;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\TranslationManager\Actions\EnsureTranslationManagerPermissionsAction;
use Capell\TranslationManager\Bridges\AIOrchestrator\PrismTranslationAITranslator;
use Capell\TranslationManager\Bridges\AIOrchestrator\TranslationManagerAIOrchestratorModule;
use Capell\TranslationManager\Contracts\TranslationAITranslator;
use Capell\TranslationManager\Contracts\TranslationFileStore;
use Capell\TranslationManager\Contracts\TranslationSourceResolver;
use Capell\TranslationManager\Support\ConfigTranslationSourceResolver;
use Capell\TranslationManager\Support\FileTranslationFileStore;
use Capell\TranslationManager\Support\NullTranslationAITranslator;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Event;
use Override;
use Spatie\LaravelPackageTools\Package;

final class TranslationManagerServiceProvider extends AbstractPackageServiceProvider
{
    public static string $name = 'capell-translation-manager';

    public static string $packageName = 'capell-app/translation-manager';

    #[Override]
    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile('capell-translation-manager')
            ->hasTranslations()
            ->hasViews(self::$name)
            ->hasMigration('2026_07_19_120000_create_translation_scan_runs_table');
    }

    #[Override]
    public function registeringPackage(): void
    {
        parent::registeringPackage();

        $this
            ->registerBindings();
    }

    #[Override]
    protected function bootInstalledRuntime(): void
    {
        $this->ensurePermissions();
        $this->registerOptionalAIOrchestratorIntegration();
    }

    #[Override]
    protected function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(self::$packageName);
    }

    private function registerBindings(): self
    {
        $this->app->singleton(TranslationSourceResolver::class, ConfigTranslationSourceResolver::class);
        $this->app->singleton(TranslationFileStore::class, FileTranslationFileStore::class);
        $this->app->singleton(TranslationAITranslator::class, NullTranslationAITranslator::class);

        return $this;
    }

    private function registerOptionalAIOrchestratorIntegration(): self
    {
        if (! CapellCore::isPackageAvailable('capell-app/ai-orchestrator')) {
            return $this;
        }

        if ($this->app->getProvider(AIOrchestratorServiceProvider::class) === null) {
            return $this;
        }

        $this->app->singleton(TranslationAITranslator::class, PrismTranslationAITranslator::class);

        $this->app->booted(function (): void {
            $this->callAfterResolving(
                AIOrchestratorModuleRegistry::class,
                function (AIOrchestratorModuleRegistry $registry): void {
                    $registry->register(new TranslationManagerAIOrchestratorModule);
                },
            );
        });

        return $this;
    }

    private function ensurePermissions(): void
    {
        // The permission rows are only read once someone is signed in, so the sync waits for
        // the first authenticated user instead of probing the schema while the app boots.
        $synced = false;

        Event::listen(Authenticated::class, static function () use (&$synced): void {
            if ($synced) {
                return;
            }

            $synced = true;
            $table = config('permission.table_names.permissions', 'permissions');

            if (is_string($table) && resolve(RuntimeSchemaState::class)->hasTable($table)) {
                EnsureTranslationManagerPermissionsAction::run();
            }
        });
    }
}
