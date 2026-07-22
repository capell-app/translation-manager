<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Integrations\AI;

use Capell\AIOrchestrator\Support\Ai\PrismProvider;
use Capell\TranslationManager\Contracts\TranslationAITranslator;
use Capell\TranslationManager\Data\AITranslationSuggestionData;
use Capell\TranslationManager\Data\TranslationEntryData;
use JsonException;
use UnexpectedValueException;

final readonly class PrismTranslationAITranslator implements TranslationAITranslator
{
    public function __construct(private PrismProvider $provider) {}

    public function available(): bool
    {
        return $this->provider->isAvailable();
    }

    public function translateSelected(string $sourceLocale, string $targetLocale, array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        $model = $this->configuredString('model', 'gpt-4o');
        $maxTokens = max(1, $this->configuredInteger('max_tokens', 2000));
        $messages = $this->messages($sourceLocale, $targetLocale, $entries);
        $encodedMessages = json_encode($messages, JSON_THROW_ON_ERROR);

        $response = $this->provider->chat([
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $this->configuredFloat('temperature', 0.1),
            'idempotency_key' => hash('sha256', implode('|', [
                'translation-manager',
                $sourceLocale,
                $targetLocale,
                $encodedMessages,
            ])),
        ]);
        $translations = $this->translationsFromResponse($response->content);

        return array_values(array_filter(array_map(
            static function (TranslationEntryData $entry) use ($translations): ?AITranslationSuggestionData {
                $translation = $translations[$entry->key] ?? null;

                if (! is_string($translation) || trim($translation) === '') {
                    return null;
                }

                return new AITranslationSuggestionData(
                    key: $entry->key,
                    value: $translation,
                );
            },
            $entries,
        )));
    }

    /**
     * @param  array<int, TranslationEntryData>  $entries
     * @return list<array{role: string, content: string}>
     */
    private function messages(string $sourceLocale, string $targetLocale, array $entries): array
    {
        $sourceEntries = array_map(
            static fn (TranslationEntryData $entry): array => [
                'key' => $entry->key,
                'source' => $entry->sourceValue,
            ],
            $entries,
        );

        return [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'You translate Laravel localisation strings for a human review workflow.',
                    'Return JSON only in the shape {"translations":{"exact.key":"translation"}}.',
                    'Preserve every input key exactly and do not add keys.',
                    'Preserve placeholders such as :name, {count}, %s, HTML tags, and Laravel plural forms.',
                    'Translate meaning and tone without adding explanations.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'source_locale' => $sourceLocale,
                    'target_locale' => $targetLocale,
                    'entries' => $sourceEntries,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ],
        ];
    }

    /** @return array<string, string> */
    private function translationsFromResponse(string $content): array
    {
        $content = trim($content);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $content, $matches) === 1) {
            $content = trim($matches[1]);
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('AI translation response must be valid JSON.', previous: $exception);
        }

        $translations = is_array($decoded) ? ($decoded['translations'] ?? null) : null;

        if (! is_array($translations) || array_is_list($translations)) {
            throw new UnexpectedValueException('AI translation response must contain a translations object.');
        }

        return array_filter(
            $translations,
            static fn (mixed $translation, mixed $key): bool => is_string($key) && is_string($translation),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function configuredString(string $key, string $fallback): string
    {
        $value = config("capell-translation-manager.ai.{$key}", $fallback);

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    private function configuredInteger(string $key, int $fallback): int
    {
        $value = config("capell-translation-manager.ai.{$key}", $fallback);

        return is_numeric($value) ? (int) $value : $fallback;
    }

    private function configuredFloat(string $key, float $fallback): float
    {
        $value = config("capell-translation-manager.ai.{$key}", $fallback);

        return is_numeric($value) ? (float) $value : $fallback;
    }
}
