<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static list<array{index: int, key: string, sourceValue: string|null, targetValue: string|null, status: string, editable: bool}> run(list<array{index: int, key: string, sourceValue: string|null, targetValue: string|null, status: string, editable: bool}> $entries, string $filter)
 */
final class FilterTranslationEntriesAction
{
    use AsObject;

    /**
     * @param  list<array{index: int, key: string, sourceValue: string|null, targetValue: string|null, status: string, editable: bool}>  $entries
     * @return list<array{index: int, key: string, sourceValue: string|null, targetValue: string|null, status: string, editable: bool}>
     */
    public function handle(array $entries, string $filter): array
    {
        if ($filter === 'all') {
            return $entries;
        }

        return array_values(array_filter(
            $entries,
            static fn (array $entry): bool => $filter === 'needs_attention'
                ? in_array($entry['status'], ['missing', 'stale', 'changed'], true)
                : $entry['status'] === $filter,
        ));
    }
}
