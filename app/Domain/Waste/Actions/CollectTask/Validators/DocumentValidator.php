<?php

namespace App\Domain\Waste\Actions\CollectTask\Validators;

use App\Domain\Document\Models\Document;
use App\Domain\Waste\Models\CollectTask;
use App\Domain\Waste\Enums\CollectTaskBlocker;
use Carbon\Carbon;
use Illuminate\Support\Collection;

// Responsável por validar documentos obrigatórios.
class DocumentValidator
{
    public static function validate(CollectTask $collectTask, Collection $requiredDocumentTypes): array
    {
        $blockers = [];

        // Percorre todos os tipos obrigatórios.
        foreach ($requiredDocumentTypes as $docType)
        {
            // Busca documento correspondente.
            $doc = $collectTask->wasteGenerationPoint->documents->firstWhere('document_type_id', $docType->id);

            // Documento não encontrado.
            if (!$doc) {

                $blockers[] = CollectTaskBlocker::MISSING_REQUIRED_DOCUMENTS->value;
                continue;
            }

            // Documento vencido.
            if ($doc->expires_at && Carbon::parse($doc->expires_at)->lt(today()))
            {
                $blockers[] = CollectTaskBlocker::EXPIRED_REQUIRED_DOCUMENTS->value;
            }

            // Documento inválido.
            if ($doc->status === Document::STATUS_INVALID)
            {
                $blockers[] = CollectTaskBlocker::INVALID_REQUIRED_DOCUMENTS->value;
            }
        }

        // Remove duplicidades.
        return array_values(array_unique($blockers));
    }
}