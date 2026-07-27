<?php

declare(strict_types=1);

use Capell\TranslationManager\Data\TranslationEntryData;
use Capell\TranslationManager\Integrations\AI\PrismTranslationAITranslator;
use Capell\TranslationManager\Tests\Fixtures\TranslationPrismProviderFake;
use Capell\TranslationManager\Tests\TranslationManagerTestCase;

uses(TranslationManagerTestCase::class);

it('rejects suggestions that alter protected translation structure', function (string $source, string $translation): void {
    $translator = new PrismTranslationAITranslator(new TranslationPrismProviderFake(json_encode([
        'translations' => ['message' => $translation],
    ], JSON_THROW_ON_ERROR)));

    $suggestions = $translator->translateSelected('en', 'fr', [
        new TranslationEntryData('message', $source, null, 'missing', true),
    ]);

    expect($suggestions)->toBe([]);
})->with([
    'Laravel placeholder removed' => ['Welcome, :name', 'Bienvenue'],
    'braced placeholder changed' => ['Hello {name}', 'Bonjour {user}'],
    'printf placeholder changed' => ['Downloaded %1$s of %2$d files', 'Téléchargé %1$s fichiers'],
    'HTML tag removed' => ['Read <strong>:count</strong> results', 'Lire :count résultats'],
    'HTML tag order changed' => ['Read <strong><em>this</em></strong>', 'Lire <em><strong>ceci</strong></em>'],
    'plural branch removed' => ['{0} None|{1} One|[2,*] :count items', '{0} Aucun|[2,*] :count éléments'],
    'plural selector changed' => ['{0} None|[1,*] :count items', '{1} Aucun|[1,*] :count éléments'],
]);

it('returns suggestions when protected translation structure is preserved', function (): void {
    $translator = new PrismTranslationAITranslator(new TranslationPrismProviderFake(
        '{"translations":{"message":"<strong>Bonjour :name</strong>, vous avez %1$d article|<strong>Bonjour :name</strong>, vous avez %1$d articles"}}',
    ));

    $suggestions = $translator->translateSelected('en', 'fr', [
        new TranslationEntryData(
            'message',
            '<strong>Hello :name</strong>, you have %1$d item|<strong>Hello :name</strong>, you have %1$d items',
            null,
            'missing',
            true,
        ),
    ]);

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]->key)->toBe('message');
});
