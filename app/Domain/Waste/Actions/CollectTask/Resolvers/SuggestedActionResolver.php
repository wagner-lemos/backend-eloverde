<?php
namespace App\Domain\Waste\Actions\CollectTask\Resolvers;
use App\Domain\Waste\Enums\SuggestedAction;

/*
    Responsável por transformar uma lista de bloqueios em uma ação sugerida.
    Ex: Bloqueios: ['missing_required_documents']
    Resultado: review_documents
*/
class SuggestedActionResolver
{
    // Recebe a lista de bloqueios encontrados durante a análise da coleta.
    public static function resolve(array $blockers): string
    {
        // Se não existe nenhum bloqueio, a coleta está apta para execução.
        if (empty($blockers)) {
            return SuggestedAction::EXECUTE->value;
        }

        // Lista de todos os bloqueios relacionados exclusivamente a documentação.
        // Motivo: Precisei identificar quando uma coleta possui somente problemas documentais.
        $documentBlockers = ['missing_required_documents', 'expired_required_documents', 'invalid_required_documents'];

        /*
            Verifica se existe apenas um único bloqueio. E se esse bloqueio é "invalid_state".
            Ex. válido: ['invalid_state']
            Resultado esperado: "fix_state"
        */
        $onlyState =
            // Deve existir exatamente 1 bloqueio, e esse bloqueio deve ser invalid_state
            count($blockers) === 1 && in_array('invalid_state', $blockers);

        /*
            Verifica se existe apenas um bloqueio de duplicidade.
            Ex: ['duplicate_collect_for_same_day']
            Resultado esperado: "review_or_merge"
        */
        $onlyDuplicate =

            // Deve existir exatamente 1 bloqueio, e esse bloqueio deve ser duplicidade
            count($blockers) === 1 && in_array('duplicate_collect_for_same_day', $blockers);

        /*
            Verifica se TODOS os bloqueios encontrados são documentais.
            Ex: ['missing_required_documents', 'expired_required_documents']
            Deve retornar TRUE.

            Ex: ['missing_required_documents', 'invalid_state']
            Deve retornar FALSE.
        */
        $onlyDocuments =

            // Compara quantidade total de bloqueios com Quantidade de bloqueios que pertencem à lista de bloqueios documentais.
            // Se os números forem iguais, significa que todos os bloqueios são documentais.
            count($blockers) === count(array_intersect($blockers, $documentBlockers));

        // Se o único problema é estado, sugere correção de estado.
        if ($onlyState) {
            return SuggestedAction::FIX_STATE->value;
        }

        // Se todos os problemas encontrados são documentais, sugere revisão documental.
        if ($onlyDocuments) {
            return SuggestedAction::REVIEW_DOCUMENTS->value;
        }

        // Se o único problema é duplicidade, sugere revisão ou merge.
        if ($onlyDuplicate) {
            return SuggestedAction::REVIEW_OR_MERGE->value;
        }

        /*
            Caso exista uma combinação de problemas diferentes.
            Ex:
                ['invalid_state', 'missing_required_documents']
                Ou
                ['duplicate_collect_for_same_day', 'expired_required_documents']
            
            A regra de negocio determina: manual_review
        */
        return SuggestedAction::MANUAL_REVIEW->value;
    }
}
