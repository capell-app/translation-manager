<?php

declare(strict_types=1);

use Capell\TranslationManager\Actions\TranslateSelectedEntriesAction;
use Capell\TranslationManager\Contracts\TranslationAITranslator;
use Capell\TranslationManager\Data\AITranslationSuggestionData;
use Capell\TranslationManager\Data\TranslationEntryData;
use Capell\TranslationManager\Support\NullTranslationAITranslator;
use Capell\TranslationManager\Tests\TranslationManagerTestCase;

uses(TranslationManagerTestCase::class);

it('translates only selected editable entries through the translator binding', function (): void {
    app()->instance(TranslationAITranslator::class, new class implements TranslationAITranslator
    {
        public function available(): bool
        {
            return true;
        }

        public function translateSelected(string $sourceLocale, string $targetLocale, array $entries): array
        {
            return collect($entries)
                ->map(fn (TranslationEntryData $entry): AITranslationSuggestionData => new AITranslationSuggestionData(
                    key: $entry->key,
                    value: '[' . $targetLocale . '] ' . $entry->sourceValue,
                ))
                ->all();
        }
    });

    $suggestions = TranslateSelectedEntriesAction::run('en', 'fr', [
        new TranslationEntryData('title', 'Hello', null, 'missing', true),
        new TranslationEntryData('body', 'Welcome', null, 'missing', true),
        new TranslationEntryData('count', '10', null, 'missing', false),
    ], ['body', 'count']);

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]->key)->toBe('body')
        ->and($suggestions[0]->value)->toBe('[fr] Welcome');
});

it('returns no suggestions when AI translation is unavailable', function (): void {
    app()->instance(TranslationAITranslator::class, new NullTranslationAITranslator);

    $suggestions = TranslateSelectedEntriesAction::run('en', 'fr', [
        new TranslationEntryData('title', 'Hello', null, 'missing', true),
    ], ['title']);

    expect($suggestions)->toBe([]);
});
