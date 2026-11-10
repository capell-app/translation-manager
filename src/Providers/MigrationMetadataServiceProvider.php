<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Providers;

use Capell\Core\Support\Migration\MigrationRootRegistry;
use Illuminate\Support\ServiceProvider;
use Override;

final class MigrationMetadataServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {

        $this->callAfterResolving(MigrationRootRegistry::class, static function (MigrationRootRegistry $registry): void {
            $registry->register('capell-app/translation-manager', __DIR__ . '/../../database/migrations', [
                '2026_07_19_120000_create_translation_scan_runs_table' => 'capell_translation_scan_runs',
            ]);
        });
    }
}
