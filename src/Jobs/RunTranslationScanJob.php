<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Jobs;

use Capell\TranslationManager\Actions\BuildTranslationReadinessMatrixAction;
use Capell\TranslationManager\Actions\ListInstalledLocalesAction;
use Capell\TranslationManager\Actions\QueueTranslationScanAction;
use Capell\TranslationManager\Actions\ScanMissingTranslationKeysAction;
use Capell\TranslationManager\Data\LocaleSummaryData;
use Capell\TranslationManager\Models\TranslationScanRun;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

final class RunTranslationScanJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 300;

    public function __construct(public readonly int $runId) {}

    public function uniqueId(): string
    {
        return (string) $this->runId;
    }

    public function handle(): void
    {
        $run = TranslationScanRun::query()->find($this->runId);

        if (! $run instanceof TranslationScanRun || ! in_array($run->status, ['queued', 'running'], true)) {
            return;
        }

        $run->update([
            'status' => 'running',
            'started_at' => $run->started_at ?? now(),
            'error_message' => null,
        ]);

        // Keep the run eligible for retries; only failed() records terminal failure.
        $run->update([
            'status' => 'succeeded',
            'result' => $this->result($run),
            'finished_at' => now(),
        ]);
    }

    public function failed(?Throwable $throwable): void
    {
        $run = TranslationScanRun::query()->find($this->runId);

        if ($run instanceof TranslationScanRun && $run->finished_at === null) {
            $this->markFailed($run, $throwable);
        }
    }

    /** @return list<array<string, mixed>> */
    private function result(TranslationScanRun $run): array
    {
        if ($run->type === QueueTranslationScanAction::MissingKeys) {
            return array_values(array_map(
                static fn ($missingKey): array => [
                    'key' => $missingKey->key,
                    'path' => $missingKey->path,
                    'line' => $missingKey->line,
                ],
                ScanMissingTranslationKeysAction::run($run->source_key, $run->source_locale),
            ));
        }

        $locales = array_values(array_map(
            static fn (LocaleSummaryData $locale): array => [
                'locale' => $locale->locale,
                'fileCount' => $locale->fileCount,
                'sourceAvailable' => $locale->sourceAvailable,
                'overrideAvailable' => $locale->overrideAvailable,
            ],
            ListInstalledLocalesAction::run($run->source_key),
        ));

        return BuildTranslationReadinessMatrixAction::run($run->source_key, $run->source_locale, $locales);
    }

    private function markFailed(TranslationScanRun $run, ?Throwable $throwable): void
    {
        $run->update([
            'status' => 'failed',
            'error_message' => $throwable instanceof Throwable
                ? Str::limit($throwable->getMessage(), 1000, '')
                : 'Translation scan failed before completion.',
            'finished_at' => now(),
        ]);
    }
}
