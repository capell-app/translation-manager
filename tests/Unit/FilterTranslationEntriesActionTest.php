<?php

declare(strict_types=1);

use Capell\TranslationManager\Actions\FilterTranslationEntriesAction;

it('filters translation entries needing attention', function (): void {
    $entries = [
        ['index' => 0, 'key' => 'welcome', 'sourceValue' => 'Welcome', 'targetValue' => null, 'status' => 'missing', 'editable' => true],
        ['index' => 1, 'key' => 'saved', 'sourceValue' => 'Saved', 'targetValue' => 'Saved', 'status' => 'same', 'editable' => true],
        ['index' => 2, 'key' => 'changed', 'sourceValue' => 'Changed', 'targetValue' => 'Old', 'status' => 'changed', 'editable' => true],
    ];

    expect(FilterTranslationEntriesAction::run($entries, 'needs_attention'))->toHaveCount(2)
        ->and(FilterTranslationEntriesAction::run($entries, 'same'))->toBe([$entries[1]])
        ->and(FilterTranslationEntriesAction::run($entries, 'all'))->toBe($entries);
});
