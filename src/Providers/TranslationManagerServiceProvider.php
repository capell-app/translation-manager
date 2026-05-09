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
use Composer\InstalledVersions;
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
            ->registerBindings()
            ->registerPackageMetadata();

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

    private function registerPackageMetadata(): self
    {
        $packagePath = realpath(__DIR__ . '/../..');

        CapellCore::registerPackage(
            self::$packageName,
            type: self::getType(),
            serviceProviderClass: self::class,
            path: $packagePath !== false ? $packagePath : dirname(__DIR__, 2),
            version: $this->getVersion(),
            description: fn (): string => __('capell-translation-manager::package.description'),
        );

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

    private function getVersion(): string
    {
        if (! class_exists(InstalledVersions::class)) {
            return '0.0.0';
        }

        return CapellCore::getInstalledPrettyVersion(self::$packageName) ?? '0.0.0';
    }
}
