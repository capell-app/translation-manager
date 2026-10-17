<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Capell\Core\Actions\InstallPackageAction;
use Capell\Core\Facades\CapellCore;
use Capell\Tests\Support\InstalledRuntimeState;
use Capell\Tests\Support\InstalledRuntimeTestCase;
use Illuminate\Foundation\Application;

it('translates optional module metadata when its registry was resolved before package boot', function (bool $installed): void {
    InstalledRuntimeState::run(static function () use ($installed): void {
        $root = dirname(__DIR__, 3);
        $app = InstalledRuntimeTestCase::bootFromPackage($root, $installed, static function (Application $app): void {
            CapellCore::registerPackage('capell-app/ai-orchestrator', path: dirname(__DIR__, 4) . '/ai-orchestrator');
            CapellCore::forcePackageInstalled('capell-app/ai-orchestrator');
            $app->register(AIOrchestratorServiceProvider::class);
            $app->make(AIOrchestratorModuleRegistry::class);
        });

        if (! $installed) {
            InstalledRuntimeTestCase::prepareLateInstallMigrations($app);
            InstallPackageAction::run(CapellCore::getPackage('capell-app/translation-manager'));
        }

        $module = $app->make(AIOrchestratorModuleRegistry::class)->module('translation-manager');

        expect($module->label())->toBe('Translation Manager');
        expect($module->capabilities()[0]->label)->toBe('Translate selected keys');
    });
})->with([true, false]);
