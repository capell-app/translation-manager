<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Enums\AIOrchestratorApprovalLevel;
use Capell\TranslationManager\Contracts\TranslationAITranslator;
use Capell\TranslationManager\Data\AITranslationSuggestionData;
use Capell\TranslationManager\Data\LocaleSummaryData;
use Capell\TranslationManager\Data\TranslationEntryData;
use Capell\TranslationManager\Data\TranslationFileData;
use Capell\TranslationManager\Data\TranslationSourceData;
use Capell\TranslationManager\Data\TranslationWriteData;
use Capell\TranslationManager\Health\TranslationManagerHealthCheck;
use Capell\TranslationManager\Integrations\AI\DraftSelectedTranslationsAction;
use Capell\TranslationManager\Integrations\AI\TranslationManagerAIOrchestratorModule;
use Capell\TranslationManager\Support\ConfigTranslationSourceResolver;
use Capell\TranslationManager\Support\LocaleValidator;
use Capell\TranslationManager\Support\NullTranslationAITranslator;
use Capell\TranslationManager\Tests\TranslationManagerTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(TranslationManagerTestCase::class);

it('keeps translation manager values in explicit data boundaries', function (): void {
    $source = new TranslationSourceData(
        key: 'app',
        label: 'Application',
        sourcePath: lang_path(),
        overridePath: lang_path(),
        namespace: null,
        type: 'app',
        sourceWritable: true,
    );
    $namespacedSource = new TranslationSourceData(
        key: 'vendor:capell',
        label: 'Capell',
        sourcePath: lang_path('vendor/capell'),
        overridePath: lang_path('vendor/capell'),
        namespace: 'capell',
        type: 'vendor',
        sourceWritable: false,
    );

    $write = new TranslationWriteData($source, 'messages', 'en', ['hello' => 'Hello']);

    expect(new AITranslationSuggestionData('hello', 'Bonjour'))->key->toBe('hello')
        ->and(new LocaleSummaryData('en', 2, true, false))->fileCount->toBe(2)
        ->and(new TranslationEntryData('hello', 'Hello', null, 'missing', true))->editable->toBeTrue()
        ->and(new TranslationFileData('messages', 'Messages', 'php', 'en/messages.php'))->type->toBe('php')
        ->and($source->isNamespaced())->toBeFalse()
        ->and($namespacedSource->isNamespaced())->toBeTrue()
        ->and($write->values)->toBe(['hello' => 'Hello']);
});

it('validates locales and exposes a safe null AI translator', function (): void {
    $validator = new LocaleValidator;
    $translator = new NullTranslationAITranslator;

    expect($validator->isValid('en'))->toBeTrue()
        ->and($validator->isValid('pt_BR'))->toBeTrue()
        ->and($validator->isValid('../en'))->toBeFalse()
        ->and($translator->available())->toBeFalse()
        ->and($translator->translateSelected('en', 'fr', ['hello' => 'Hello']))->toBe([]);

    $validator->assertValid('fr-CA');
});

it('throws for invalid configured locale names', function (): void {
    (new LocaleValidator)->assertValid('../en');
})->throws(InvalidArgumentException::class);

it('discovers configured application and vendor translation sources', function (): void {
    $temporaryRoot = storage_path('framework/testing/translation-manager-' . Str::random(8));
    $appPath = $temporaryRoot . '/app-lang';
    $vendorPath = $temporaryRoot . '/vendor-lang';

    File::ensureDirectoryExists($appPath);
    File::ensureDirectoryExists($vendorPath);

    config([
        'capell-translation-manager.app_source' => [
            'key' => 'custom-app',
            'label' => 'Custom app',
            'path' => $appPath,
            'writable' => true,
        ],
        'capell-translation-manager.package_paths' => [],
        'capell-translation-manager.vendor_namespaces' => [
            'vendor-package' => [
                'label' => 'Vendor Package',
                'path' => $vendorPath,
                'writable' => true,
            ],
        ],
    ]);

    $sources = (new ConfigTranslationSourceResolver)->sources();

    expect(array_map(static fn (TranslationSourceData $source): string => $source->key, $sources))
        ->toContain('custom-app', 'vendor:vendor-package')
        ->and((new ConfigTranslationSourceResolver)->source('vendor:vendor-package')->namespace)
        ->toBe('vendor-package');
});

it('exposes translation manager AI module metadata and health compatibility', function (): void {
    $module = new TranslationManagerAIOrchestratorModule;
    $capabilities = $module->capabilities();

    expect($module->key())->toBe('translation-manager')
        ->and($module->label())->toBe('Translation Manager')
        ->and($capabilities)->toHaveCount(1)
        ->and($capabilities[0]->key)->toBe('translate-selected-keys')
        ->and($capabilities[0]->actionClass)->toBe(DraftSelectedTranslationsAction::class)
        ->and($capabilities[0]->approvalLevel)->toBe(AIOrchestratorApprovalLevel::Draft)
        ->and(TranslationManagerHealthCheck::compatibleCapellApiVersion())->toBe('^4.0');
});

it('drafts selected translations from AI orchestrator run context', function (): void {
    app()->instance(TranslationAITranslator::class, new class implements TranslationAITranslator
    {
        public function available(): bool
        {
            return true;
        }

        /**
         * @param  array<int, TranslationEntryData>  $entries
         * @return array<int, AITranslationSuggestionData>
         */
        public function translateSelected(string $sourceLocale, string $targetLocale, array $entries): array
        {
            return collect($entries)
                ->map(static fn (TranslationEntryData $entry): AITranslationSuggestionData => new AITranslationSuggestionData(
                    key: $entry->key,
                    value: sprintf('%s:%s:%s', $sourceLocale, $targetLocale, $entry->sourceValue),
                ))
                ->values()
                ->all();
        }
    });

    $result = DraftSelectedTranslationsAction::run(new AIOrchestratorRunData(
        moduleKey: 'translation-manager',
        capabilityKey: 'translate-selected-keys',
        prompt: 'Translate selected keys',
        context: [
            'source_locale' => 'en',
            'target_locale' => 'fr',
            'selected_keys' => ['messages.hello'],
            'entries' => [
                [
                    'key' => 'messages.hello',
                    'sourceValue' => 'Hello',
                    'targetValue' => null,
                    'status' => 'missing',
                    'editable' => true,
                ],
                [
                    'key' => '',
                    'sourceValue' => 'Ignored',
                ],
                'not-an-entry',
            ],
        ],
    ));

    expect($result)->toBe([
        [
            'key' => 'messages.hello',
            'value' => 'en:fr:Hello',
        ],
    ]);
});
