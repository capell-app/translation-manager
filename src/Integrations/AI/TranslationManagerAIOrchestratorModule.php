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
        return (string) __('capell-translation-manager::package.translation_manager');
    }

    public function capabilities(): array
    {
        return [
            new AIOrchestratorCapabilityData(
                key: 'translate-selected-keys',
                label: (string) __('capell-translation-manager::package.translate_selected_keys'),
                description: (string) __('capell-translation-manager::package.translate_selected_keys_description'),
                actionClass: DraftSelectedTranslationsAction::class,
                approvalLevel: AIOrchestratorApprovalLevel::Draft,
            ),
        ];
    }
}
