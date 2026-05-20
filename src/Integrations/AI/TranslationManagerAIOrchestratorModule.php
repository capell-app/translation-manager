<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Integrations\AI;

use Capell\AIOrchestrator\Contracts\AIOrchestratorModule;
use Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData;
use Capell\AIOrchestrator\Enums\AIOrchestratorApprovalLevel;

final class TranslationManagerAIOrchestratorModule implements AIOrchestratorModule
{
    public function key(): string
    {
        return 'translation-manager';
    }

    public function label(): string
    {
        return 'Translation Manager';
    }

    public function capabilities(): array
    {
        return [
            new AIOrchestratorCapabilityData(
                key: 'translate-selected-keys',
                label: 'Translate selected keys',
                description: 'Draft target locale values for selected Laravel translation keys.',
                actionClass: DraftSelectedTranslationsAction::class,
                approvalLevel: AIOrchestratorApprovalLevel::Draft,
            ),
        ];
    }
}
