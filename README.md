# Interview Backend Eloverde

## Solução Implementada
A implementação foi desenvolvida seguindo os princípios de separação de responsabilidades (SRP), mantendo cada regra de negócio isolada em componentes específicos para facilitar manutenção, testes e futuras evoluções.

A análise de pré-execução foi dividida em validadores independentes:

* **StateValidator**: responsável por validar se a coleta está no estado `programming`.
* **DocumentValidator**: responsável por verificar documentos obrigatórios, vencidos ou inválidos.
* **DuplicateCollectValidator**: responsável por identificar coletas duplicadas considerando mesma data, mesmo ponto gerador e mesmo conjunto de resíduos, independentemente da ordem dos resíduos.
* **SuggestedActionResolver**: responsável por determinar a ação sugerida com base nos bloqueios encontrados.
* **PreExecutionResultSorter**: responsável por aplicar as regras de ordenação exigidas pelo desafio.

Além da implementação das regras de negócio, toda a solução foi amplamente documentada diretamente no código-fonte. Cada classe, método e decisão relevante possui comentários explicando não apenas o que está sendo feito, mas também os motivos da implementação, as regras de negócio atendidas e as decisões técnicas adotadas. O objetivo foi tornar a solução facilmente compreensível para qualquer desenvolvedor que precise dar manutenção ou evoluir a funcionalidade futuramente.

Durante a implementação também foram adicionados testes complementares para cenários que não estavam totalmente cobertos pela suíte original, incluindo:

* Identificação de coletas duplicadas.
* Seleção da coleta relacionada mais antiga em cenários com múltiplas duplicidades.
* Combinação de bloqueios de naturezas diferentes.
* Validação das regras de ordenação por prioridade, bloqueios e data agendada.

O desenvolvimento foi conduzido com foco em legibilidade, manutenibilidade, testabilidade e aderência às regras de negócio descritas no desafio, buscando demonstrar não apenas a implementação funcional da solução, mas também a organização do código, a clareza arquitetural e o raciocínio utilizado durante o processo de desenvolvimento.

O resultado final retorna, para cada coleta analisada, sua elegibilidade para execução, bloqueios identificados, ação sugerida e eventual relacionamento com outra coleta quando caracterizada duplicidade.

## Request

```json
{
  "collect_task_ids": [1,2,3,4,5,6,7,8,9]
}
```

## Response

```json
{
	"data": [
		{
			"collect_task_id": 9,
			"can_execute": false,
			"priority": "high",
			"blockers": [
				"invalid_required_documents"
			],
			"suggested_action": "review_documents",
			"related_collect_task_id": null
		},
		{
			"collect_task_id": 6,
			"can_execute": true,
			"priority": "high",
			"blockers": [],
			"suggested_action": "execute",
			"related_collect_task_id": null
		},
		{
			"collect_task_id": 2,
			"can_execute": false,
			"priority": "normal",
			"blockers": [
				"missing_required_documents"
			],
			"suggested_action": "review_documents",
			"related_collect_task_id": null
		},
		{
			"collect_task_id": 3,
			"can_execute": false,
			"priority": "normal",
			"blockers": [
				"expired_required_documents"
			],
			"suggested_action": "review_documents",
			"related_collect_task_id": null
		},
		{
			"collect_task_id": 4,
			"can_execute": false,
			"priority": "normal",
			"blockers": [
				"invalid_state"
			],
			"suggested_action": "fix_state",
			"related_collect_task_id": null
		},
		{
			"collect_task_id": 5,
			"can_execute": false,
			"priority": "normal",
			"blockers": [
				"invalid_state",
				"missing_required_documents"
			],
			"suggested_action": "manual_review",
			"related_collect_task_id": null
		},
		{
			"collect_task_id": 7,
			"can_execute": false,
			"priority": "normal",
			"blockers": [
				"duplicate_collect_for_same_day"
			],
			"suggested_action": "review_or_merge",
			"related_collect_task_id": 8
		},
		{
			"collect_task_id": 8,
			"can_execute": false,
			"priority": "normal",
			"blockers": [
				"duplicate_collect_for_same_day"
			],
			"suggested_action": "review_or_merge",
			"related_collect_task_id": 7
		},
		{
			"collect_task_id": 1,
			"can_execute": true,
			"priority": "normal",
			"blockers": [],
			"suggested_action": "execute",
			"related_collect_task_id": null
		}
	]
}
```
