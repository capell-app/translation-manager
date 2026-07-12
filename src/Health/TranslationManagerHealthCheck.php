<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Health;

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\TranslationManager\Contracts\TranslationAITranslator;
use Capell\TranslationManager\Contracts\TranslationFileStore;
use Capell\TranslationManager\Contracts\TranslationSourceResolver;
use Capell\TranslationManager\Filament\Pages\TranslationManagerPage;
use Capell\TranslationManager\Manifest\TranslationManagerPageContribution;
use Capell\TranslationManager\Providers\AdminServiceProvider;
use Capell\TranslationManager\Providers\TranslationManagerServiceProvider;
use Illuminate\Support\Collection;
use Throwable;

final class TranslationManagerHealthCheck implements ChecksExtensionHealth
{
    /**
     * @var list<class-string>
     */
    private const array REQUIRED_BINDINGS = [
        TranslationSourceResolver::class,
        TranslationFileStore::class,
        TranslationAITranslator::class,
    ];

    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }

    /**
     * @return Collection<int, DoctorCheckResultData>
     */
    public static function runDiagnostics(): Collection
    {
        $check = new self;

        return collect([
            $check->adminSurfaceCheck(),
            $check->serviceBindingsCheck(),
            $check->configurationCheck(),
        ]);
    }

    public static function passed(): bool
    {
        return self::runDiagnostics()
            ->every(static fn (DoctorCheckResultData $result): bool => $result->passed);
    }

    /**
     * Asserts the admin page contribution classes are available for Diagnostics.
     */
    public function adminSurfaceCheck(): DoctorCheckResultData
    {
        $available = class_exists(TranslationManagerPage::class)
            && class_exists(TranslationManagerPageContribution::class)
            && class_exists(TranslationManagerServiceProvider::class)
            && class_exists(AdminServiceProvider::class);

        return new DoctorCheckResultData(
            label: 'Translation Manager admin surface',
            passed: $available,
            message: $available
                ? 'The translation manager page, contribution, and providers are available.'
                : 'The translation manager admin page, contribution, or providers are missing.',
            remediation: $available
                ? null
                : 'Ensure Translation Manager is installed with its runtime and admin providers.',
        );
    }

    /**
     * Asserts the filesystem source/store and AI translator contracts resolve.
     */
    public function serviceBindingsCheck(): DoctorCheckResultData
    {
        $unresolvableBindings = $this->unresolvableBindings();

        return new DoctorCheckResultData(
            label: 'Translation Manager service bindings',
            passed: $unresolvableBindings === [],
            message: $unresolvableBindings === []
                ? 'The translation source resolver, file store, and AI translator contracts are resolvable.'
                : 'Unresolvable bindings: ' . implode(', ', $unresolvableBindings) . '.',
            remediation: $unresolvableBindings === []
                ? null
                : 'Ensure TranslationManagerServiceProvider registers the translation manager contracts.',
        );
    }

    /**
     * Asserts the package configuration required for safe filesystem access is loaded.
     */
    public function configurationCheck(): DoctorCheckResultData
    {
        $readable = $this->isConfigurationReadable();

        return new DoctorCheckResultData(
            label: 'Translation Manager configuration',
            passed: $readable,
            message: $readable
                ? 'The source locale, locale pattern, app source, and package write guard configuration are readable.'
                : 'The translation manager configuration is not loaded.',
            remediation: $readable
                ? null
                : 'Ensure TranslationManagerServiceProvider merges config/capell-translation-manager.php.',
        );
    }

    /**
     * @return list<class-string>
     */
    public function unresolvableBindings(): array
    {
        $bindings = [];

        foreach (self::REQUIRED_BINDINGS as $binding) {
            try {
                if (! resolve($binding) instanceof $binding) {
                    $bindings[] = $binding;
                }
            } catch (Throwable) {
                $bindings[] = $binding;
            }
        }

        return $bindings;
    }

    public function isConfigurationReadable(): bool
    {
        return config()->has('capell-translation-manager.source_locale')
            && config()->has('capell-translation-manager.locale_pattern')
            && config()->has('capell-translation-manager.app_source')
            && config()->has('capell-translation-manager.package_source_writes');
    }
}
