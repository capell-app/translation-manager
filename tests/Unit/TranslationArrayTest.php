<?php

declare(strict_types=1);

use Capell\TranslationManager\Support\LocaleValidator;
use Capell\TranslationManager\Support\TranslationArray;
use Capell\TranslationManager\Tests\TranslationManagerTestCase;

uses(TranslationManagerTestCase::class);

it('flattens nested translation arrays for editing', function (): void {
    $entries = TranslationArray::flattenForEditor([
        'title' => 'Hello',
        'nested' => [
            'body' => 'Welcome',
            'count' => 10,
        ],
    ]);

    expect($entries['title'])->toBe([
        'value' => 'Hello',
        'editable' => true,
        'exists' => true,
    ])->and($entries['nested.body']['value'])->toBe('Welcome')
        ->and($entries['nested.count']['value'])->toBe('10')
        ->and($entries['nested.count']['editable'])->toBeFalse();
});

it('unflattens string entries into nested arrays', function (): void {
    expect(TranslationArray::unflattenStrings([
        'title' => 'Bonjour',
        'nested.body' => 'Bienvenue',
    ]))->toBe([
        'title' => 'Bonjour',
        'nested' => [
            'body' => 'Bienvenue',
        ],
    ]);
});

it('rejects unsafe locale names', function (): void {
    resolve(LocaleValidator::class)->assertValid('../en');
})->throws(InvalidArgumentException::class);
