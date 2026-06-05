<?php
namespace App\Domain\Waste\Actions\CollectTask\Validators;

use App\Domain\Waste\Models\CollectTask;
use App\Domain\Waste\Enums\CollectTaskState;
use App\Domain\Waste\Enums\CollectTaskBlocker;

// Class Responsável por validar se a coleta está no estado correto para execução.
class StateValidator
{
    public static function validate(CollectTask $collectTask): array
    {
        // Apenas coletas no estado PROGRAMMING podem seguir para execução.
        if ($collectTask->state !== CollectTaskState::PROGRAMMING->value)
        {
            return [CollectTaskBlocker::INVALID_STATE->value];
        }

        return [];
    }
}