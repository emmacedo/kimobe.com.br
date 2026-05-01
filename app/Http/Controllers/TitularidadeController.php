<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTitularidadeRequest;
use App\Http\Requests\UpdateTitularidadeRequest;
use App\Models\Imovel;
use App\Models\Scopes\TenantScope;
use App\Models\Titularidade;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TitularidadeController extends Controller
{
    /**
     * Adiciona um titular ao imóvel. Validações de unicidade, soma de percentuais
     * e conta bancária estão em StoreTitularidadeRequest. Comportamento radio:
     * ao marcar como responsável, demove os demais titulares para 'observador'.
     */
    public function store(StoreTitularidadeRequest $request, Imovel $imovel): JsonResponse
    {
        $tenantId = app(TenantService::class)->getTenantId();
        $dados = $request->validated();

        $titularidade = DB::transaction(function () use ($imovel, $dados, $tenantId) {
            if ($dados['papel'] === 'responsavel') {
                $this->demoteOutrosResponsaveis($imovel->id);
            }

            // Se há registro soft-deletado para este (imovel, vinculo), restaura em vez
            // de criar novo — o unique index (imovel_id, vinculo_id) inclui linhas trashed.
            $trashed = Titularidade::onlyTrashed()
                ->withoutGlobalScopes([TenantScope::class])
                ->where('imovel_id', $imovel->id)
                ->where('vinculo_id', $dados['vinculo_id'])
                ->first();

            if ($trashed) {
                $trashed->restore();
                $trashed->update($dados);

                return $trashed;
            }

            return Titularidade::create([
                'tenant_id' => $tenantId,
                'imovel_id' => $imovel->id,
                ...$dados,
            ]);
        });

        $titularidade->load(['vinculo.user', 'dadosBancarios']);

        return response()->json($titularidade, 201);
    }

    /**
     * Atualiza um titular. Não permite trocar o proprietário (vinculo_id).
     * Mantém comportamento radio.
     */
    public function update(UpdateTitularidadeRequest $request, Imovel $imovel, Titularidade $titularidade): JsonResponse
    {
        // Garante que a titularidade pertence ao imóvel da URL (mitigação IDOR).
        abort_unless($titularidade->imovel_id === $imovel->id, 404);

        $dados = $request->validated();

        $titularidade = DB::transaction(function () use ($imovel, $titularidade, $dados) {
            if ($dados['papel'] === 'responsavel') {
                $this->demoteOutrosResponsaveis($imovel->id, $titularidade->id);
            }

            $titularidade->update($dados);

            return $titularidade;
        });

        $titularidade->load(['vinculo.user', 'dadosBancarios']);

        return response()->json($titularidade);
    }

    /**
     * Soft-deleta um titular. Não permite remover se há repasses vinculados.
     * Auto-promove o próximo titular para responsável quando o removido era o atual.
     */
    public function destroy(Imovel $imovel, Titularidade $titularidade): JsonResponse
    {
        // Garante que a titularidade pertence ao imóvel da URL (mitigação IDOR).
        abort_unless($titularidade->imovel_id === $imovel->id, 404);

        $temRepasses = $titularidade->repasses()
            ->withoutGlobalScopes([TenantScope::class])
            ->exists();

        if ($temRepasses) {
            return response()->json([
                'message' => 'Não é possível remover este titular pois existem repasses vinculados.',
            ], 422);
        }

        DB::transaction(function () use ($imovel, $titularidade) {
            $eraResponsavel = $titularidade->papel === 'responsavel';
            $titularidade->delete();

            if ($eraResponsavel) {
                // Promove o próximo titular ATIVO restante para responsável.
                $proximo = Titularidade::withoutGlobalScopes([TenantScope::class])
                    ->where('imovel_id', $imovel->id)
                    ->orderBy('id')
                    ->first();

                $proximo?->update(['papel' => 'responsavel']);
            }
        });

        return response()->json(['message' => 'Titular removido.']);
    }

    /**
     * Demove para 'observador' todos os titulares responsáveis de um imóvel,
     * exceto o `$exceptId` informado. Reutilizado por store e update.
     */
    private function demoteOutrosResponsaveis(int $imovelId, ?int $exceptId = null): void
    {
        $query = Titularidade::withoutGlobalScopes([TenantScope::class])
            ->where('imovel_id', $imovelId)
            ->where('papel', 'responsavel');

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['papel' => 'observador']);
    }
}
