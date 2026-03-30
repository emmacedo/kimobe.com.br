<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\ContratoResponsabilidade;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContratoResponsabilidadeController extends Controller
{
    /**
     * Cria uma ou mais responsabilidades para o contrato.
     * Aceita item único ou array de itens (batch pré-definidos).
     */
    public function store(Request $request, Contrato $contrato): JsonResponse
    {
        $tenantId = app(TenantService::class)->getTenantId();

        // Batch: array de itens (pré-definidos)
        if ($request->has('itens')) {
            $request->validate([
                'itens' => ['required', 'array', 'min:1'],
                'itens.*.descricao' => ['required', 'string', 'max:255'],
                'itens.*.responsavel' => ['required', 'in:proprietario,inquilino'],
                'itens.*.valor' => ['nullable', 'numeric', 'min:0'],
                'itens.*.periodicidade' => ['required', 'in:mensal,anual,avulso'],
                'itens.*.predefinido' => ['boolean'],
                'itens.*.observacoes' => ['nullable', 'string', 'max:2000'],
            ]);

            $criados = DB::transaction(function () use ($request, $contrato, $tenantId) {
                $result = [];
                foreach ($request->input('itens') as $item) {
                    $result[] = ContratoResponsabilidade::create([
                        'tenant_id' => $tenantId,
                        'contrato_id' => $contrato->id,
                        'descricao' => $item['descricao'],
                        'responsavel' => $item['responsavel'],
                        'valor' => $item['valor'] ?? null,
                        'periodicidade' => $item['periodicidade'],
                        'predefinido' => $item['predefinido'] ?? true,
                        'observacoes' => $item['observacoes'] ?? null,
                    ]);
                }
                return $result;
            });

            return response()->json($criados, 201);
        }

        // Item único
        $request->validate([
            'descricao' => ['required', 'string', 'max:255'],
            'responsavel' => ['required', 'in:proprietario,inquilino'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'periodicidade' => ['required', 'in:mensal,anual,avulso'],
            'predefinido' => ['boolean'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ], [
            'descricao.required' => 'Informe a descrição da responsabilidade.',
            'responsavel.required' => 'Selecione o responsável.',
            'periodicidade.required' => 'Selecione a periodicidade.',
        ]);

        $resp = ContratoResponsabilidade::create([
            'tenant_id' => $tenantId,
            'contrato_id' => $contrato->id,
            'descricao' => $request->descricao,
            'responsavel' => $request->responsavel,
            'valor' => $request->valor,
            'periodicidade' => $request->periodicidade,
            'predefinido' => $request->boolean('predefinido', false),
            'observacoes' => $request->observacoes,
        ]);

        return response()->json($resp, 201);
    }

    /**
     * Atualiza uma responsabilidade do contrato.
     */
    public function update(Request $request, Contrato $contrato, ContratoResponsabilidade $responsabilidade): JsonResponse
    {
        $request->validate([
            'descricao' => ['required', 'string', 'max:255'],
            'responsavel' => ['required', 'in:proprietario,inquilino'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'periodicidade' => ['required', 'in:mensal,anual,avulso'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);

        $responsabilidade->update($request->only(['descricao', 'responsavel', 'valor', 'periodicidade', 'observacoes']));

        return response()->json($responsabilidade);
    }

    /**
     * Remove uma responsabilidade do contrato.
     */
    public function destroy(Contrato $contrato, ContratoResponsabilidade $responsabilidade): JsonResponse
    {
        $responsabilidade->delete();

        return response()->json(['message' => 'Responsabilidade removida.']);
    }
}
