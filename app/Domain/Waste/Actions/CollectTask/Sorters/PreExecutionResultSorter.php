<?php
namespace App\Domain\Waste\Actions\CollectTask\Sorters;

use App\Domain\Waste\DTO\CollectTask\PreExecutionCheckResultDTO;
use App\Domain\Waste\Models\CollectTask;
use Illuminate\Support\Collection;

// Class responsável pela ordenação dos resultados.
class PreExecutionResultSorter
{
    /*
        Ordena os resultados seguindo as regras:

        1) Coletas urgentes primeiro
        2) Dentro do mesmo nível de urgência: bloqueadas antes das aptas
        3) Persistindo empate: scheduled_to crescente
    */
    public static function sort(Collection $results, Collection $collectTasks): Collection
    {
        /*
            Cria um mapa: [ collect_task_id => scheduled_to ]
            Motivo: O DTO não possui scheduled_to.

            Como a regra de ordenação depende da data agendada, precisei criar um índice rápido para consultar essa informação. Ex: [ 10 => Carbon(...), 11 => Carbon(...), 12 => Carbon(...) ]
        */
        $scheduledMap = $collectTasks->mapWithKeys(

            // Para cada coleta: Usa o ID como chave e scheduled_to como valor
            fn (CollectTask $task) => [$task->id =>$task->scheduled_to]
        );

        return $results

            /*
                Callback de comparação.
                O sort irá comparar dois elementos por vez.
                $a = item atual
                $b = item sendo comparado
            */
            ->sort(function (PreExecutionCheckResultDTO $a, PreExecutionCheckResultDTO $b) use ($scheduledMap) {

                //REGRA 1: Coletas urgentes devem aparecer primeiro.

                // Verifica se o item A é urgente.
                $aUrgent = $a->priority === 'high';

                // Verifica se o item B é urgente.
                $bUrgent = $b->priority === 'high';

                // Se um é urgente e o outro não, a decisão já pode ser tomada.
                if ($aUrgent !== $bUrgent) {

                    /*
                        Se B for urgente e A não: retorna positivo
                        Se A for urgente e B não: retorna negativo
                        Resultado: urgentes ficam primeiro.
                    */
                    return $bUrgent <=> $aUrgent;
                }

                // REGRA 2: Dentro do mesmo nível de urgência, coletas bloqueadas devem aparecer antes das coletas aptas.

                // Possui bloqueios?
                $aBlocked = !empty($a->blockers);
                // Possui bloqueios?
                $bBlocked = !empty($b->blockers);

                // Se um está bloqueado e o outro não, já conseguimos decidir.
                if ($aBlocked !== $bBlocked) {
                    return $bBlocked <=> $aBlocked;
                }

                //REGRA 3: Persistindo empate, ordena por scheduled_to crescente.
                // Ex: Nesta ordem: 08:00, 09:00, 10:00
                return

                    // Timestamp da coleta A e Timestamp da coleta B.
                    $scheduledMap[$a->collect_task_id]->timestamp <=> $scheduledMap[$b->collect_task_id]->timestamp;
            })->values();
    }
}
