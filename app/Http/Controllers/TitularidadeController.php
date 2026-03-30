<?php

namespace App\Http\Controllers;

use App\Models\Imovel;
use App\Models\Titularidade;
use App\Models\Vinculo;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TitularidadeController extends Controller
{
    /**
     * Adiciona um titular ao imóvel.
     * Valida unicidade do vínculo e soma de percentuais.
     */
    public function store(Request $request, Imovel $imovel): JsonResponse
    {
        $tenantId = app(TenantService::class)->getTenantId();

        $request->validate([
            'vinculo_id' => ['required', 'integer'],
            'tipo_titular' => ['required', 'in:pessoa_fisica,empresa,inventario'],
            'papel' => ['required', 'in:responsavel,observador'],
            'percentual' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'dados_bancarios_id' => ['nullable', 'integer'],
        ], [
            'vinculo_id.required' => 'Selecione o proprietário.',
            'tipo_titular.required' => 'Selecione o tipo de titular.',
            'papel.required' => 'Selecione o papel do titular.',
            'percentual.required' => 'Informe o percentual de propriedade.',
            'percentual.min' => 'O percentual deve ser maior que zero.',
            'percentual.max' => 'O percentual não pode ultrapassar 100%.',
        ]);

        // Verificar que o vínculo é um proprietário do mesmo tenant
        $vinculo = Vinculo::where('id', $request->vinculo_id)
            ->where('tenant_id', $tenantId)
            ->where('papel', 'proprietario')
            ->first();

        if (! $vinculo) {
            return response()->json(['message' => 'Proprietário não encontrado.'], 422);
        }

        // Verificar unicidade: vínculo não pode já ser titular deste imóvel
        $jaExiste = Titularidade::withoutGlobalScopes()
            ->where('imovel_id', $imovel->id)
            ->where('vinculo_id', $request->vinculo_id)
            ->exists();

        if ($jaExiste) {
            return response()->json(['message' => 'Este proprietário já é titular deste imóvel.'], 422);
        }

        // Validar soma de percentuais
        $somaAtual = Titularidade::withoutGlobalScopes()
            ->where('imovel_id', $imovel->id)
            ->sum('percentual');

        if (($somaAtual + $request->percentual) > 100.005) { // margem para floating point
            return response()->json([
                'message' => 'A soma dos percentuais ultrapassaria 100%. Disponível: ' . number_format(100 - $somaAtual, 2) . '%.',
            ], 422);
        }

        // Validar dados bancários pertencem ao vínculo
        if ($request->dados_bancarios_id) {
            $contaValida = $vinculo->dadosBancarios()
                ->where('id', $request->dados_bancarios_id)
                ->exists();

            if (! $contaValida) {
                return response()->json(['message' => 'Conta bancária inválida.'], 422);
            }
        }

        $titularidade = Titularidade::create([
            'tenant_id' => $tenantId,
            'imovel_id' => $imovel->id,
            'vinculo_id' => $request->vinculo_id,
            'tipo_titular' => $request->tipo_titular,
            'papel' => $request->papel,
            'percentual' => $request->percentual,
            'dados_bancarios_id' => $request->dados_bancarios_id,
        ]);

        $titularidade->load(['vinculo.user', 'dadosBancarios']);

        return response()->json($titularidade, 201);
    }

    /**
     * Atualiza um titular do imóvel.
     * Não permite trocar o proprietário (vinculo_id), apenas tipo, papel, percentual e conta.
     */
    public function update(Request $request, Imovel $imovel, Titularidade $titularidade): JsonResponse
    {
        $request->validate([
            'tipo_titular' => ['required', 'in:pessoa_fisica,empresa,inventario'],
            'papel' => ['required', 'in:responsavel,observador'],
            'percentual' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'dados_bancarios_id' => ['nullable', 'integer'],
        ]);

        // Validar soma de percentuais (excluindo o percentual atual desta titularidade)
        $somaOutros = Titularidade::withoutGlobalScopes()
            ->where('imovel_id', $imovel->id)
            ->where('id', '!=', $titularidade->id)
            ->sum('percentual');

        if (($somaOutros + $request->percentual) > 100.005) {
            return response()->json([
                'message' => 'A soma dos percentuais ultrapassaria 100%. Disponível: ' . number_format(100 - $somaOutros, 2) . '%.',
            ], 422);
        }

        $titularidade->update([
            'tipo_titular' => $request->tipo_titular,
            'papel' => $request->papel,
            'percentual' => $request->percentual,
            'dados_bancarios_id' => $request->dados_bancarios_id,
        ]);

        $titularidade->load(['vinculo.user', 'dadosBancarios']);

        return response()->json($titularidade);
    }

    /**
     * Remove um titular do imóvel.
     * Não permite remover se existem repasses vinculados.
     */
    public function destroy(Imovel $imovel, Titularidade $titularidade): JsonResponse
    {
        // Verificar se tem repasses vinculados
        $temRepasses = $titularidade->repasses()
            ->withoutGlobalScopes()
            ->exists();

        if ($temRepasses) {
            return response()->json([
                'message' => 'Não é possível remover este titular pois existem repasses vinculados.',
            ], 422);
        }

        $titularidade->delete();

        return response()->json(['message' => 'Titular removido.']);
    }
}
