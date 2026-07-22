<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Tests\Fixtures;

use Capell\AIOrchestrator\Support\Ai\AiResponse;
use Capell\AIOrchestrator\Support\Ai\PrismProvider;

final class TranslationPrismProviderFake extends PrismProvider
{
    /** @var list<array<array-key, mixed>> */
    public array $requests = [];

    public function __construct(private readonly string $responseContent)
    {
        parent::__construct([]);
    }

    public function chat(array $params): AiResponse
    {
        $this->requests[] = $params;

        return new AiResponse(
            content: $this->responseContent,
            tokensUsed: 24,
            model: is_string($params['model'] ?? null) ? $params['model'] : 'gpt-4o',
            duration: 0.01,
            metadata: [
                'prompt_tokens' => 16,
                'completion_tokens' => 8,
            ],
        );
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
