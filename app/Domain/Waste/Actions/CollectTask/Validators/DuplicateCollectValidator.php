<?php
namespace App\Domain\Waste\Actions\CollectTask\Validators;

use App\Domain\Waste\Models\CollectTask;
use App\Domain\Waste\Enums\CollectTaskBlocker;

// Validador responsável por identificar coletas duplicadas no mesmo dia.

/* 
    Regras:

    - Mesmo dia
    - Mesmo ponto gerador
    - Mesmo conjunto de resíduos
    - A ordem dos resíduos não importa
    - A própria coleta não pode ser comparada consigo mesma
    - Em caso de múltiplas duplicidades, retorna a mais antiga
*/
class DuplicateCollectValidator
{
    // Executa a validação de duplicidade.
    public static function validate(CollectTask $collectTask): array
    {
        // Obtém todos os resíduos da coleta atual.
        $ownWasteIds = $collectTask->items->pluck('waste_id')->unique()
            // Ordena os IDs para normalizar a comparação e reorganiza os índices do array
            // Ex: [3,1,2] vira [1,2,3]
            ->sort()->values()->all();

        /*
            Busca coletas de possíveis duplicidade
            Filtra apenas coletas do mesmo dia
            OBS: O requisito fala "mesmo dia", portanto ignorei o horário e filtrei pelo mesmo ponto gerador
        */
        $duplicates = CollectTask::query()->with('items')
            ->whereDate('scheduled_to', $collectTask->scheduled_to->toDateString())
            ->where('waste_generation_point_id', $collectTask->waste_generation_point_id)

            // Exclui a própria coleta da busca para evitar que ela seja considerada duplicada dela mesma
            ->where('id', '!=', $collectTask->id)->get()

            // Mantém apenas as coletas que possuem exatamente o mesmo conjunto de resíduos
            ->filter(function ($candidate) use ($ownWasteIds) {

                // Obtém os resíduos da coleta candidata
                $candidateWasteIds = $candidate->items->pluck('waste_id')->unique()->sort()->values()->all();

                // Compara os dois conjuntos de resíduos.
                // Ex: [1,2,3] será igual a [3,2,1]
                return $candidateWasteIds === $ownWasteIds;
            });

        // Se nenhuma duplicidade foi encontrada
        if ($duplicates->isEmpty()) {

            return ['blockers' => [], 'related_collect_task_id' => null];
        }

        // Retorna bloqueio de duplicidade, bloqueio exigido pela regra de negocio
        return [
            'blockers' => [CollectTaskBlocker::DUPLICATE_COLLECT_FOR_SAME_DAY->value],

            // Retorna a coleta mais antiga dentre as duplicadas.
            // Ordena pela data de criação, e retorna a mais antiga primeiro
            'related_collect_task_id' => $duplicates->sortBy('created_at')->first()->id,
        ];
    }
}