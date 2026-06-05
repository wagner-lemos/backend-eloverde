<?php
namespace App\Domain\Waste\Actions\CollectTask;

use App\Domain\Document\Models\DocumentType;
use App\Domain\Waste\DTO\CollectTask\PreExecutionCheckResultDTO;
use App\Domain\Waste\Actions\CollectTask\Validators\DocumentValidator;
use App\Domain\Waste\Actions\CollectTask\Validators\DuplicateCollectValidator;
use App\Domain\Waste\Actions\CollectTask\Validators\StateValidator;
use App\Domain\Waste\Actions\CollectTask\Resolvers\SuggestedActionResolver;
use App\Domain\Waste\Actions\CollectTask\Sorters\PreExecutionResultSorter;
use App\Domain\Waste\Models\CollectTask;
use Illuminate\Support\Collection;

class PreExecutionCheckAction
{
    public static function execute(Collection $collectTaskIds): Collection
    {
        // Busca todos os tipos de documentos obrigatórios cadastrados no sistema.
        $requiredDocumentTypes = DocumentType::where('is_required', true)->get();

        // Carrega todas as coletas solicitadas já trazendo os relacionamentos necessários para evitar N+1.
        // no parametros: Resíduos associados à coleta e Documentos do ponto gerador
        $collectTasks = CollectTask::query()->with(['items', 'wasteGenerationPoint.documents.documentType'])
            ->whereIn('id', $collectTaskIds)
            ->get();

        // Analisa cada coleta individualmente.
        $results = $collectTasks->map(function (CollectTask $collectTask) use ($requiredDocumentTypes) {

            // Lista de bloqueios encontrados.
            $blockers = [];

            // Validação do estado da coleta.
            $blockers = array_merge($blockers, StateValidator::validate($collectTask));

            // Validação dos documentos obrigatórios.
            $blockers = array_merge($blockers, DocumentValidator::validate($collectTask, $requiredDocumentTypes));

            // Validação de duplicidade.
            $duplicateResult = DuplicateCollectValidator::validate($collectTask);

            // Adiciona bloqueios encontrados na duplicidade.
            $blockers = array_merge($blockers, $duplicateResult['blockers']);

            // Remove possíveis duplicações de bloqueios.
            $blockers = array_values(array_unique($blockers));

            // Monta DTO de retorno.
            return new PreExecutionCheckResultDTO(

                // ID da coleta analisada
                collect_task_id: $collectTask->id,

                // Pode executar apenas se não houver bloqueios
                can_execute: empty($blockers),

                // Define prioridade baseada na urgência
                priority: $collectTask->is_urgent ? 'high' : 'normal',

                // Lista de bloqueios encontrados
                blockers: $blockers,

                // Determina ação sugerida
                suggested_action: SuggestedActionResolver::resolve($blockers),

                // Coleta relacionada em caso de duplicidade
                related_collect_task_id: $duplicateResult['related_collect_task_id'],
            );
        });

        /*
            Ordena o resultado conforme regras de negócio:
            1. Urgentes primeiro
            2. Bloqueadas antes das aptas
            3. scheduled_to crescente
        */
        return PreExecutionResultSorter::sort($results, $collectTasks);
    }
}