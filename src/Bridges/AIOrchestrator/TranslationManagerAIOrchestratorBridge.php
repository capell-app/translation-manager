<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Bridges\AIOrchestrator;

use Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Capell\Core\Facades\CapellCore;
use Capell\TranslationManager\Contracts\TranslationAITranslator;
use Illuminate\Contracts\Foundation\Application;

/** Wires AI-drafted translations into AI Orchestrator once its provider has booted into the application. */
final class TranslationManagerAIOrchestratorBridge
{
    public static function register(Application $app): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/ai-orchestrator')) {
            return;
        }

        if ($app->getProvider(AIOrchestratorServiceProvider::class) === null) {
            return;
        }

        $app->singleton(TranslationAITranslator::class, PrismTranslationAITranslator::class);

        $app->booted(static function () use ($app): void {
            $register = static function (AIOrchestratorModuleRegistry $registry): void {
                $registry->register(new TranslationManagerAIOrchestratorModule);
            };

            $app->afterResolving(AIOrchestratorModuleRegistry::class, $register);

            if ($app->resolved(AIOrchestratorModuleRegistry::class)) {
                $register($app->make(AIOrchestratorModuleRegistry::class));
            }
        });
    }
}
