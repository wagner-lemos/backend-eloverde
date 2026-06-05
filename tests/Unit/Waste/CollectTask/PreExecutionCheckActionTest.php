<?php

use App\Domain\Waste\Actions\CollectTask\PreExecutionCheckAction;
use Database\Seeders\InterviewChallengeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(InterviewChallengeSeeder::class);
});

function runPreExecutionCheck(array $collectTaskIds): Collection
{
    return PreExecutionCheckAction::execute(Collection::make($collectTaskIds));
}

test('it considers a collect task executable when it is in programming and has valid required documents', function (): void {
    $result = runPreExecutionCheck([1])->first();

    expect($result->collect_task_id)->toBe(1);
    expect($result->can_execute)->toBeTrue();
    expect($result->blockers)->toBe([]);
    expect($result->suggested_action)->toBe('execute');
});

test('it blocks collect tasks that are not in programming state', function (): void {
    $result = runPreExecutionCheck([4])->first();

    expect($result->collect_task_id)->toBe(4);
    expect($result->can_execute)->toBeFalse();
    expect($result->blockers)->toContain('invalid_state');
    expect($result->suggested_action)->toBe('fix_state');
});

test('it blocks collect tasks with missing required documents', function (): void {
    $result = runPreExecutionCheck([2])->first();

    expect($result->collect_task_id)->toBe(2);
    expect($result->can_execute)->toBeFalse();
    expect($result->blockers)->toContain('missing_required_documents');
    expect($result->suggested_action)->toBe('review_documents');
});

test('it blocks collect tasks with expired required documents', function (): void {
    $result = runPreExecutionCheck([3])->first();

    expect($result->collect_task_id)->toBe(3);
    expect($result->can_execute)->toBeFalse();
    expect($result->blockers)->toContain('expired_required_documents');
    expect($result->suggested_action)->toBe('review_documents');
});

test('it blocks collect tasks with invalid required documents', function (): void {
    $result = runPreExecutionCheck([9])->first();

    expect($result->collect_task_id)->toBe(9);
    expect($result->can_execute)->toBeFalse();
    expect($result->blockers)->toContain('invalid_required_documents');
    expect($result->suggested_action)->toBe('review_documents');
});

test('it returns multiple blockers when a collect task has more than one problem', function (): void {
    $result = runPreExecutionCheck([5])->first();

    expect($result->collect_task_id)->toBe(5);
    expect($result->can_execute)->toBeFalse();
    expect($result->blockers)->toContain('invalid_state');
    expect($result->blockers)->toContain('missing_required_documents');
    expect($result->suggested_action)->toBe('manual_review');
});

// Inicia os testes implementados para cobrir lacunas abertas informados na regra de negocio
test('it selects the oldest related collect when multiple duplicates exist',function (): void {

    // Cria um ponto gerador que será utilizado pelas coletas
    $point = \App\Domain\Waste\Models\WasteGenerationPoint::create([

        // Nome do ponto gerador
        'name' => 'Ponto MultiDup',

        // Código interno
        'internal_code' => 'WGP-009',

        // Marca como ativo
        'active' => true,
    ]);

    // Busca todos os tipos de documentos obrigatórios
    $requiredDocTypes = \App\Domain\Document\Models\DocumentType::where('is_required', true)->get();

    // Cria documentos válidos para cada tipo obrigatório
    foreach ($requiredDocTypes as $dt)
    {
        \App\Domain\Document\Models\Document::create([

            // Tipo do documento
            'document_type_id' => $dt->id,

            // Classe relacionada ao documento
            'documentable_type' => \App\Domain\Waste\Models\WasteGenerationPoint::class,

            // ID do ponto gerador
            'documentable_id' => $point->id,

            // Documento válido
            'status' => \App\Domain\Document\Models\Document::STATUS_VALID,

            // Documento ainda não vencido
            'expires_at' => now()->addDays(10),
        ]);
    }

    // Cria o primeiro resíduo
    $wasteA = \App\Domain\Waste\Models\Waste::create(['name' => 'WA', 'code' => 'WA', 'unit' => 'kg']);

    // Cria o segundo resíduo
    $wasteB = \App\Domain\Waste\Models\Waste::create(['name' => 'WB', 'code' => 'WB', 'unit' => 'kg']);

    // Define a mesma data para todas as coletas
    $scheduled = now()->addDays(5)->setTime(9, 0, 0);

    // Cria a coleta mais antiga
    $oldest = \App\Domain\Waste\Models\CollectTask::create([

            // Mesmo ponto gerador
            'waste_generation_point_id' => $point->id,

            // Mesma data
            'scheduled_to' => $scheduled,

            // Estado válido
            'state' => \App\Domain\Waste\Models\CollectTask::STATE_PROGRAMMING,

            // Não urgente
            'is_urgent' => false,
        ]);

    // Adiciona o resíduo A
    $oldest->items()->create(['waste_id' => $wasteA->id, 'expected_quantity' => 1]);

    // Adiciona o resíduo B
    $oldest->items()->create(['waste_id' => $wasteB->id, 'expected_quantity' => 1]);

    // Força esta coleta a ser a mais antiga
    $oldest->update(['created_at' => now()->subDays(4)]);

    // Cria a coleta intermediária
    $middle = \App\Domain\Waste\Models\CollectTask::create([

            'waste_generation_point_id' => $point->id,
            'scheduled_to' => $scheduled,
            'state' => \App\Domain\Waste\Models\CollectTask::STATE_PROGRAMMING,
            'is_urgent' => false,
        ]);

    // Mesmo conjunto de resíduos
    $middle->items()->create(['waste_id' => $wasteA->id, 'expected_quantity' => 1]);
    $middle->items()->create(['waste_id' => $wasteB->id, 'expected_quantity' => 1]);

    // Define como segunda mais antiga
    $middle->update(['created_at' => now()->subDays(2)]);

    // Cria a coleta mais nova
    $newest = \App\Domain\Waste\Models\CollectTask::create([

            'waste_generation_point_id' => $point->id,
            'scheduled_to' => $scheduled,
            'state' => \App\Domain\Waste\Models\CollectTask::STATE_PROGRAMMING,
            'is_urgent' => false,
        ]);

    // Insere os resíduos em ordem invertida, para provar que a ordem não interfere
    $newest->items()->create(['waste_id' => $wasteB->id, 'expected_quantity' => 1]);
    $newest->items()->create(['waste_id' => $wasteA->id, 'expected_quantity' => 1]);

    // Executa a regra de pré-validação
    $results = runPreExecutionCheck([$oldest->id, $middle->id, $newest->id]);

    // Busca o resultado da coleta mais nova
    $resNewest = $results->firstWhere('collect_task_id', $newest->id);

    // Garante que encontrou o resultado
    expect($resNewest)->not->toBeNull();

    // Deve possuir bloqueio de duplicidade
    expect($resNewest->blockers)->toContain('duplicate_collect_for_same_day');

    // Deve apontar para a coleta mais antiga
    expect($resNewest->related_collect_task_id)->toBe($oldest->id);

    // Deve sugerir revisão ou merge
    expect($resNewest->suggested_action)->toBe('review_or_merge');
});