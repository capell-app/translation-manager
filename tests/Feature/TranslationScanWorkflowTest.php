<?php

declare(strict_types=1);

use Capell\TranslationManager\Actions\QueueTranslationScanAction;
use Capell\TranslationManager\Actions\ScanMissingTranslationKeysAction;
use Capell\TranslationManager\Jobs\RunTranslationScanJob;
use Capell\TranslationManager\Models\TranslationScanRun;
use Capell\TranslationManager\Tests\TranslationManagerTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(TranslationManagerTestCase::class);

function translationScanRunId(TranslationScanRun $run): int
{
    $runId = $run->getKey();
    throw_unless(is_int($runId), RuntimeException::class, 'Translation scan run must have an integer key.');

    return $runId;
}

beforeEach(function (): void {
    $this->translationBasePath = sys_get_temp_dir() . '/capell-translation-scan/' . Str::uuid()->toString();
    $this->appLanguagePath = $this->translationBasePath . '/lang';

    File::ensureDirectoryExists($this->appLanguagePath . '/en');
    File::ensureDirectoryExists($this->appLanguagePath . '/fr');
    File::ensureDirectoryExists($this->translationBasePath . '/views');

    config()->set('capell-translation-manager.app_source.path', $this->appLanguagePath);
    config()->set('capell-translation-manager.package_paths', []);
    config()->set('capell-translation-manager.scan_paths', [$this->translationBasePath . '/views']);

    File::put($this->appLanguagePath . '/en/messages.php', "<?php\n\nreturn ['title' => 'Hello'];\n");
    File::put($this->appLanguagePath . '/fr/messages.php', "<?php\n\nreturn ['title' => ''];\n");
    File::put($this->translationBasePath . '/views/example.blade.php', "{{ __('messages.missing') }}");
});

afterEach(function (): void {
    File::deleteDirectory($this->translationBasePath);
});

it('deduplicates queued scans and persists their progress and result', function (): void {
    Queue::fake();

    $first = QueueTranslationScanAction::run(QueueTranslationScanAction::Readiness, 'app', 'en');
    $second = QueueTranslationScanAction::run(QueueTranslationScanAction::Readiness, 'app', 'en');

    expect($second->is($first))->toBeTrue()
        ->and($first->status)->toBe('queued');

    Queue::assertPushed(RunTranslationScanJob::class, 1);

    (new RunTranslationScanJob(translationScanRunId($first)))->handle();

    $first->refresh();

    expect($first->status)->toBe('succeeded')
        ->and($first->started_at)->not->toBeNull()
        ->and($first->finished_at)->not->toBeNull()
        ->and($first->result)->toHaveCount(1)
        ->and(data_get($first->result, '0.locale'))->toBe('fr');
});

it('persists missing key scan results outside the Livewire request', function (): void {
    Queue::fake();
    $run = QueueTranslationScanAction::run(QueueTranslationScanAction::MissingKeys, 'app', 'en');

    (new RunTranslationScanJob(translationScanRunId($run)))->handle();

    expect($run->refresh()->status)->toBe('succeeded')
        ->and(data_get($run->result, '0.key'))->toBe('messages.missing');
});

it('keeps a scan retryable after a transient exception', function (): void {
    $run = TranslationScanRun::query()->create([
        'type' => QueueTranslationScanAction::MissingKeys,
        'source_key' => 'test-source',
        'source_locale' => 'en',
        'status' => 'queued',
    ]);
    $action = Mockery::mock(new ScanMissingTranslationKeysAction);
    app()->instance('LaravelActions:AsFake:' . ScanMissingTranslationKeysAction::class, $action);
    $calls = 0;
    $action->shouldReceive('handle')->twice()->andReturnUsing(function () use (&$calls): array {
        if (++$calls === 1) {
            throw new RuntimeException('Temporary scan failure');
        }

        return [];
    });
    $job = new RunTranslationScanJob(translationScanRunId($run));
    expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'Temporary scan failure');
    expect($run->refresh()->status)->toBe('running')
        ->and($run->finished_at)->toBeNull();
    $job->handle();
    expect($run->refresh()->status)->toBe('succeeded')
        ->and($run->finished_at)->not->toBeNull();
});

it('persists a bounded error when a queued scan exhausts its retries', function (): void {
    $run = TranslationScanRun::query()->create([
        'type' => QueueTranslationScanAction::Readiness,
        'source_key' => 'unknown-source',
        'source_locale' => 'en',
        'status' => 'queued',
    ]);

    $threw = false;

    try {
        (new RunTranslationScanJob(translationScanRunId($run)))->handle();
    } catch (Throwable $throwable) {
        $threw = true;
        expect($run->refresh()->status)->toBe('running')
            ->and($run->finished_at)->toBeNull();
        (new RunTranslationScanJob(translationScanRunId($run)))->failed($throwable);
    }

    expect($threw)->toBeTrue()
        ->and($run->refresh()->status)->toBe('failed')
        ->and($run->error_message)->not->toBeEmpty()
        ->and(mb_strlen((string) $run->error_message))->toBeLessThanOrEqual(1000);
});
