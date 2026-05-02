<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\ContratoInquilino;
use App\Models\Scopes\TenantScope;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContratoInquilinoController extends Controller
{
    /**
     * Adiciona um inquilino ao contrato.
     * Radio behavior: marcar como principal demove os outros e atualiza o cache
     * em contratos.inquilino_vinculo_id.
     */
    public function store(Request $request, Contrato $contrato): JsonResponse
    {
        $tenantId = app(TenantService::class)->getTenantId();

        $request->validate([
            'vinculo_id' => [
                'required', 'integer',
                Rule::exists('vinculos', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('papel', 'inquilino')
                    ->where('status', 'ativo'),
            ],
            'principal' => ['required', 'boolean'],
        ], [
            'vinculo_id.required' => 'Selecione o inquilino.',
            'vinculo_id.exists' => 'Inquilino não encontrado.',
        ]);

        $ci = DB::transaction(function () use ($contrato, $request, $tenantId) {
            // Verifica unicidade dentro da transação com lockForUpdate para evitar
            // race condition em cliques duplos.
            $jaExisteAtivo = ContratoInquilino::withoutGlobalScopes([TenantScope::class])
                ->where('contrato_id', $contrato->id)
                ->where('vinculo_id', $request->vinculo_id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->exists();

            if ($jaExisteAtivo) {
                return null;
            }

            if ($request->boolean('principal')) {
                $this->demoteOutrosPrincipais($contrato->id);
            }

            // Restaura registro soft-deletado se existir.
            $trashed = ContratoInquilino::onlyTrashed()
                ->withoutGlobalScopes([TenantScope::class])
                ->where('contrato_id', $contrato->id)
                ->where('vinculo_id', $request->vinculo_id)
                ->first();

            if ($trashed) {
                $trashed->restore();
                $trashed->update(['principal' => $request->boolean('principal')]);
                $ci = $trashed;
            } else {
                $ci = ContratoInquilino::create([
                    'tenant_id' => $tenantId,
                    'contrato_id' => $contrato->id,
                    'vinculo_id' => $request->vinculo_id,
                    'principal' => $request->boolean('principal'),
                ]);
            }

            // Sincroniza cache via método único que sempre lê o principal atual.
            $this->ressincronizarCachePrincipal($contrato);

            return $ci;
        });

        if ($ci === null) {
            return response()->json(['message' => 'Este inquilino já está vinculado ao contrato.'], 422);
        }

        $ci->load('vinculo.user');

        return response()->json($ci, 201);
    }

    /**
     * Atualiza apenas o flag 'principal'. Não permite trocar o vinculo_id.
     * Se virar principal, demove os outros e atualiza o cache.
     * Se virar não-principal e era o único: erro (precisa ter ao menos 1 principal).
     */
    public function update(Request $request, Contrato $contrato, ContratoInquilino $contratoInquilino): JsonResponse
    {
        // IDOR guard: garante que o registro pertence ao contrato da URL.
        abort_unless($contratoInquilino->contrato_id === $contrato->id, 404);

        $request->validate(['principal' => ['required', 'boolean']]);

        $virouNaoPrincipal = $contratoInquilino->principal && ! $request->boolean('principal');

        if ($virouNaoPrincipal) {
            // Se este era o único principal, bloqueia — todo contrato precisa de 1 principal.
            $outrosPrincipais = ContratoInquilino::withoutGlobalScopes([TenantScope::class])
                ->where('contrato_id', $contrato->id)
                ->where('id', '!=', $contratoInquilino->id)
                ->where('principal', true)
                ->exists();

            if (! $outrosPrincipais) {
                return response()->json([
                    'message' => 'O contrato precisa de um inquilino principal. Marque outro inquilino como principal primeiro.',
                ], 422);
            }
        }

        $ci = DB::transaction(function () use ($contrato, $contratoInquilino, $request) {
            if ($request->boolean('principal')) {
                $this->demoteOutrosPrincipais($contrato->id, $contratoInquilino->id);
            }

            $contratoInquilino->update(['principal' => $request->boolean('principal')]);

            // Sincroniza cache: lê o principal atual no banco (pode ter mudado para outro vínculo
            // se este foi demovido). Garante consistência entre cache e a pivot.
            $this->ressincronizarCachePrincipal($contrato);

            return $contratoInquilino;
        });

        $ci->load('vinculo.user');

        return response()->json($ci);
    }

    /**
     * Remove um inquilino do contrato (soft delete). Bloqueia se for o último.
     * Se era o principal, auto-promove o próximo restante e atualiza o cache.
     */
    public function destroy(Contrato $contrato, ContratoInquilino $contratoInquilino): JsonResponse
    {
        abort_unless($contratoInquilino->contrato_id === $contrato->id, 404);

        $totalAtivos = ContratoInquilino::withoutGlobalScopes([TenantScope::class])
            ->where('contrato_id', $contrato->id)
            ->count();

        if ($totalAtivos <= 1) {
            return response()->json([
                'message' => 'Não é possível remover — o contrato precisa de pelo menos 1 inquilino.',
            ], 422);
        }

        $novoPrincipalId = DB::transaction(function () use ($contrato, $contratoInquilino) {
            $eraPrincipal = $contratoInquilino->principal;
            $contratoInquilino->delete();

            if ($eraPrincipal) {
                // Auto-promove o próximo (ordem por id).
                $proximo = ContratoInquilino::withoutGlobalScopes([TenantScope::class])
                    ->where('contrato_id', $contrato->id)
                    ->orderBy('id')
                    ->first();

                if ($proximo) {
                    $proximo->update(['principal' => true]);
                    $this->ressincronizarCachePrincipal($contrato);

                    return $proximo->id;
                }
            }

            return null;
        });

        return response()->json([
            'message' => 'Inquilino removido.',
            'novo_principal_id' => $novoPrincipalId,
        ]);
    }

    /**
     * Demove para 'principal=false' todos os inquilinos do contrato exceto o $exceptId informado.
     */
    private function demoteOutrosPrincipais(int $contratoId, ?int $exceptId = null): void
    {
        $query = ContratoInquilino::withoutGlobalScopes([TenantScope::class])
            ->where('contrato_id', $contratoId)
            ->where('principal', true);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['principal' => false]);
    }

    /**
     * Lê o inquilino marcado como principal no banco e atualiza o cache em
     * contratos.inquilino_vinculo_id. Chamado sempre após mudanças no flag 'principal'
     * para garantir consistência entre o cache e a pivot.
     */
    private function ressincronizarCachePrincipal(Contrato $contrato): void
    {
        $principal = ContratoInquilino::withoutGlobalScopes([TenantScope::class])
            ->where('contrato_id', $contrato->id)
            ->where('principal', true)
            ->first();

        if ($principal) {
            $contrato->update(['inquilino_vinculo_id' => $principal->vinculo_id]);
        }
    }
}
