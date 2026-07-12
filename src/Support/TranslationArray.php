<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Support;

final class TranslationArray
{
    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array{value: string|null, editable: bool, exists: bool}>
     */
    public static function flattenForEditor(array $values, string $prefix = ''): array
    {
        $entries = [];

        foreach ($values as $key => $value) {
            $entryKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $entries += self::flattenForEditor($value, $entryKey);

                continue;
            }

            $entries[$entryKey] = [
                'value' => is_scalar($value) ? (string) $value : null,
                'editable' => is_string($value),
                'exists' => true,
            ];
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    public static function flattenStrings(array $values, string $prefix = ''): array
    {
        $entries = [];

        foreach ($values as $key => $value) {
            $entryKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $entries += self::flattenStrings($value, $entryKey);

                continue;
            }

            if (is_string($value)) {
                $entries[$entryKey] = $value;
            }
        }

        return $entries;
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, mixed>
     */
    public static function unflattenStrings(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            $result = self::setNestedValue($result, $key, $value ?? '');
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function setNestedValue(array $values, string $key, string $value): array
    {
        $segments = explode('.', $key);

        return self::setNestedSegments($values, $segments, $value);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $segments
     * @return array<string, mixed>
     */
    private static function setNestedSegments(array $values, array $segments, string $value): array
    {
        $segment = array_shift($segments);

        if ($segment === null) {
            return $values;
        }

        if ($segments === []) {
            $values[$segment] = $value;

            return $values;
        }

        $childValues = $values[$segment] ?? [];

        if (! is_array($childValues)) {
            $childValues = [];
        }

        $values[$segment] = self::setNestedSegments($childValues, $segments, $value);

        return $values;
    }
}
