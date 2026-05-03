<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemCobrancaRequest;
use App\Http\Requests\UpdateItemCobrancaRequest;
use App\Models\Contrato;
use App\Models\ItemCobranca;
use App\Services\ItemCobrancaService;
use App\Traits\ScopesPorPapel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemCobrancaController extends Controller
{
    use ScopesPorPapel;

    public function __construct(
        protected ItemCobrancaService $service,
    ) {}

    /**
     * Lista os itens (séries) de um contrato. Retorna apenas o "pai" de cada série,
     * com a contagem de ocorrências e o valor agregado.
     */
    public function index(Contrato $contrato): JsonResponse
    {
        abort_unless($this->podeVerContrato($contrato), 403);

        $series = ItemCobranca::query()
            ->where('contrato_id', $contrato->id)
            ->whereNull('parent_item_id')
            ->with('entidadeExterna:id,nome,tipo')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($series);
    }

    /**
     * Cria uma nova série de itens (com pré-geração das ocorrências).
     */
    public function store(StoreItemCobrancaRequest $request, Contrato $contrato): JsonResponse
    {
        abort_unless($this->podeVerContrato($contrato), 403);

        try {
            $item = $this->service->criar($contrato, $request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($item, 201);
    }

    /**
     * Atualiza um item conforme o `escopo` informado:
     * - somente: apenas esta ocorrência.
     * - futuras: esta e todas as futuras pendentes da série.
     * - todas: todas as pendentes da série.
     */
    public function update(UpdateItemCobrancaRequest $request, ItemCobranca $itemCobranca): JsonResponse
    {
        $contrato = $itemCobranca->contrato;
        abort_unless($this->podeVerContrato($contrato), 403);

        $dados = $request->attributesParaAtualizar();
        $escopo = $request->validated('escopo');

        try {
            $resultado = match ($escopo) {
                'somente' => ['atualizadas' => 1, 'item' => $this->service->atualizarOcorrencia($itemCobranca, $dados)],
                'futuras' => ['atualizadas' => $this->service->atualizarEstaEFuturas($itemCobranca, $dados)],
                'todas' => ['atualizadas' => $this->service->atualizarTodas($itemCobranca, $dados)],
            };
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($resultado);
    }

    /**
     * Cancela um item ou a série inteira.
     * Body opcional: `escopo=serie` para cancelar todas as pendentes da série;
     * default cancela apenas a ocorrência selecionada.
     */
    public function destroy(Request $request, ItemCobranca $itemCobranca): JsonResponse
    {
        $contrato = $itemCobranca->contrato;
        abort_unless($this->podeVerContrato($contrato), 403);

        $escopo = $request->input('escopo', 'somente');

        try {
            if ($escopo === 'serie') {
                $count = $this->service->cancelarSerie($itemCobranca);

                return response()->json(['canceladas' => $count]);
            }

            $this->service->cancelarOcorrencia($itemCobranca);

            return response()->json(['canceladas' => 1]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
