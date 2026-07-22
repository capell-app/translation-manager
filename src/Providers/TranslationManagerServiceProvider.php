<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Providers;

use Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\TranslationManager\Contracts\TranslationAITranslator;
use Capell\TranslationManager\Contracts\TranslationFileStore;
use Capell\TranslationManager\Contracts\TranslationSourceResolver;
use Capell\TranslationManager\Integrations\AI\PrismTranslationAITranslator;
use Capell\TranslationManager\Integrations\AI\TranslationManagerAIOrchestratorModule;
use Capell\TranslationManager\Support\ConfigTranslationSourceResolver;
use Capell\TranslationManager\Support\FileTranslationFileStore;
use Capell\TranslationManager\Support\NullTranslationAITranslator;
use Override;
use Spatie\LaravelPackageTools\Package;

final class TranslationManagerServiceProvider extends AbstractPackageServiceProvider
{
    private const string AI_ORCHESTRATOR_PACKAGE = 'capell-app/ai-orchestrator';

    public static string $name = 'capell-translation-manager';

    public static string $packageName = 'capell-app/translation-manager';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile('capell-translation-manager')
            ->hasTranslations()
            ->hasViews(self::$name)
            ->hasMigration('2026_07_19_120000_create_translation_scan_runs_table');
    }

    public function registeringPackage(): void
    {
        parent::registeringPackage();

        $this
            ->registerBindings();

        $this->app->booted(function (): void {
            if (! $this->isPackageInstalled()) {
                return;
            }

            $this->registerOptionalAIOrchestratorIntegration();
        });
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
        if (! CapellCore::isPackageAvailable(self::AI_ORCHESTRATOR_PACKAGE)
            || $this->app->getProvider(AIOrchestratorServiceProvider::class) === null) {
            return $this;
        }

        $this->app->singleton(TranslationAITranslator::class, PrismTranslationAITranslator::class);

        $this->app->afterResolving(
            AIOrchestratorModuleRegistry::class,
            function (AIOrchestratorModuleRegistry $registry): void {
                $registry->register(new TranslationManagerAIOrchestratorModule);
            },
        );

        return $this;
    }
}
