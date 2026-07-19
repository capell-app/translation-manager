<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Jobs\RunTranslationScanJob;
use Capell\TranslationManager\Models\TranslationScanRun;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static TranslationScanRun run(string $type, string $sourceKey, string $sourceLocale) */
final class QueueTranslationScanAction
{
    use AsFake;
    use AsObject;

    public const string Readiness = 'readiness';

    public const string MissingKeys = 'missing_keys';

    public function handle(string $type, string $sourceKey, string $sourceLocale): TranslationScanRun
    {
        throw_unless(in_array($type, [self::Readiness, self::MissingKeys], true), InvalidArgumentException::class, 'Unsupported translation scan type.');

        $lockKey = implode(':', ['capell.translation-scan', $type, hash('sha256', $sourceKey), $sourceLocale]);

        $run = Cache::lock($lockKey, 10)->get(function () use ($type, $sourceKey, $sourceLocale): TranslationScanRun {
            $activeRun = TranslationScanRun::query()
                ->where('type', $type)
                ->where('source_key', $sourceKey)
                ->where('source_locale', $sourceLocale)
                ->whereIn('status', ['queued', 'running'])
                ->latest('id')
                ->first();

            if ($activeRun instanceof TranslationScanRun) {
                return $activeRun;
            }

            $run = TranslationScanRun::query()->create([
                'type' => $type,
                'source_key' => $sourceKey,
                'source_locale' => $sourceLocale,
                'status' => 'queued',
            ]);

            $runId = $run->getKey();
            throw_unless(is_int($runId), InvalidArgumentException::class, 'Translation scan run must have an integer key.');
            RunTranslationScanJob::dispatch($runId)->afterCommit();

            return $run;
        });

        if ($run instanceof TranslationScanRun) {
            return $run;
        }

        $activeRun = TranslationScanRun::query()
            ->where('type', $type)
            ->where('source_key', $sourceKey)
            ->where('source_locale', $sourceLocale)
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->first();

        throw_unless($activeRun instanceof TranslationScanRun, InvalidArgumentException::class, 'Translation scan is being queued. Retry shortly.');

        return $activeRun;
    }
}
