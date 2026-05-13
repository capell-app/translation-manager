<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Providers;

use Capell\AIOrchestrator\Contracts\AIOrchestratorModule;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\TranslationManager\Contracts\TranslationAITranslator;
use Capell\TranslationManager\Contracts\TranslationFileStore;
use Capell\TranslationManager\Contracts\TranslationSourceResolver;
use Capell\TranslationManager\Integrations\AI\TranslationManagerAIOrchestratorModule;
use Capell\TranslationManager\Support\ConfigTranslationSourceResolver;
use Capell\TranslationManager\Support\FileTranslationFileStore;
use Capell\TranslationManager\Support\NullTranslationAITranslator;
use Spatie\LaravelPackageTools\Package;

final class TranslationManagerServiceProvider extends AbstractPackageServiceProvider
{
    public static string $name = 'capell-translation-manager';

    public static string $packageName = 'capell-app/translation-manager';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile('capell-translation-manager')
            ->hasTranslations()
            ->hasViews(self::$name);
    }

    public function registeringPackage(): void
    {
        $this
            ->registerBindings();

        $this->app->booted(function (): void {
            if (! $this->isPackageInstalled()) {
                return;
            }

            $this->registerOptionalAIOrchestratorModule();
        });
    }

    private function registerBindings(): self
    {
        $this->app->singleton(TranslationSourceResolver::class, ConfigTranslationSourceResolver::class);
        $this->app->singleton(TranslationFileStore::class, FileTranslationFileStore::class);
        $this->app->singleton(TranslationAITranslator::class, NullTranslationAITranslator::class);

        return $this;
    }

    private function registerOptionalAIOrchestratorModule(): self
    {
        if (
            ! interface_exists(AIOrchestratorModule::class)
            || ! class_exists(AIOrchestratorModuleRegistry::class)
        ) {
            return $this;
        }

        $this->app->afterResolving(
            AIOrchestratorModuleRegistry::class,
            function (object $registry): void {
                if (method_exists($registry, 'register')) {
                    $registry->register(new TranslationManagerAIOrchestratorModule);
                }
            },
        );

        return $this;
    }

    private function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(self::$packageName);
    }
}
