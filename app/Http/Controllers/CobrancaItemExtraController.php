<?php

namespace App\Http\Controllers;

use App\Models\Cobranca;
use App\Models\CobrancaItemExtra;
use App\Services\CobrancaService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CobrancaItemExtraController extends Controller
{
    public function __construct(
        protected CobrancaService $cobrancaService,
    ) {}

    public function store(Request $request, Cobranca $cobranca): JsonResponse
    {
        if (in_array($cobranca->status, ['pago', 'cancelado'])) {
            return response()->json(['message' => 'Não é possível adicionar itens a cobranças pagas ou canceladas.'], 422);
        }

        $request->validate([
            'descricao' => ['required', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);

        $item = CobrancaItemExtra::create([
            'tenant_id' => app(TenantService::class)->getTenantId(),
            'cobranca_id' => $cobranca->id,
            'descricao' => $request->descricao,
            'valor' => $request->valor,
            'observacoes' => $request->observacoes,
        ]);

        $this->cobrancaService->recalcularTotal($cobranca);

        return response()->json([
            'item' => $item,
            'valor_total' => $cobranca->fresh()->valor_total,
        ], 201);
    }

    public function update(Request $request, Cobranca $cobranca, CobrancaItemExtra $item): JsonResponse
    {
        if (in_array($cobranca->status, ['pago', 'cancelado'])) {
            return response()->json(['message' => 'Não é possível editar itens de cobranças pagas ou canceladas.'], 422);
        }

        $request->validate([
            'descricao' => ['required', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);

        $item->update($request->only(['descricao', 'valor', 'observacoes']));
        $this->cobrancaService->recalcularTotal($cobranca);

        return response()->json([
            'item' => $item,
            'valor_total' => $cobranca->fresh()->valor_total,
        ]);
    }

    public function destroy(Cobranca $cobranca, CobrancaItemExtra $item): JsonResponse
    {
        if (in_array($cobranca->status, ['pago', 'cancelado'])) {
            return response()->json(['message' => 'Não é possível remover itens de cobranças pagas ou canceladas.'], 422);
        }

        $item->delete();
        $this->cobrancaService->recalcularTotal($cobranca);

        return response()->json([
            'message' => 'Item removido.',
            'valor_total' => $cobranca->fresh()->valor_total,
        ]);
    }
}
