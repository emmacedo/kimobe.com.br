<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertCondominioRequest;
use App\Models\Imovel;
use Illuminate\Http\RedirectResponse;

class CondominioController extends Controller
{
    /**
     * Cria ou atualiza os dados de condomínio de um imóvel.
     * Restaura registro soft-deleted antes de fazer upsert para evitar conflito
     * com a unique constraint em imovel_id.
     */
    public function upsert(UpsertCondominioRequest $request, Imovel $imovel): RedirectResponse
    {
        $existente = $imovel->condominio()->withTrashed()->first();
        if ($existente && $existente->trashed()) {
            $existente->restore();
        }

        $imovel->condominio()->updateOrCreate([], $request->validated());

        return back()->with('success', 'Dados de condomínio salvos com sucesso.');
    }

    /**
     * Remove os dados de condomínio do imóvel (soft delete).
     */
    public function destroy(Imovel $imovel): RedirectResponse
    {
        $imovel->condominio?->delete();

        return back()->with('success', 'Dados de condomínio removidos.');
    }
}
