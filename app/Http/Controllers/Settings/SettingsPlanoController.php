<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Imovel;
use App\Models\Plano;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gerencia visualização e alteração do plano do assinante.
 */
class SettingsPlanoController extends Controller
{
    public function __construct(
        private TenantService $tenantService,
    ) {}

    /**
     * Página "Meu plano" — plano atual, uso, faturas.
     */
    public function index(): Response
    {
        $tenant = $this->tenantService->getTenant();
        $tenant->load('planoAssinatura');

        // Contagem de imóveis do tenant
        $imoveisCount = Imovel::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();

        // Faturas do Kimobe
        $faturas = $tenant->faturasKimobe()
            ->orderByDesc('referencia')
            ->limit(20)
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'referencia' => $f->referencia,
                'valor' => $f->valor,
                'data_vencimento' => $f->data_vencimento?->format('d/m/Y'),
                'data_pagamento' => $f->data_pagamento?->format('d/m/Y'),
                'status' => $f->status,
            ]);

        // Planos ativos para o dialog de alteração
        $planosAtivos = Plano::where('status', 'ativo')->orderBy('ordem')->get()->map(fn ($p) => [
            'id' => $p->id,
            'nome' => $p->nome,
            'descricao' => $p->descricao,
            'limite_imoveis' => $p->limite_imoveis,
            'valor_mensal' => $p->valor_mensal,
        ]);

        return Inertia::render('settings/plano', [
            'plano' => $tenant->planoAssinatura ? [
                'id' => $tenant->planoAssinatura->id,
                'nome' => $tenant->planoAssinatura->nome,
                'valor_mensal' => $tenant->planoAssinatura->valor_mensal,
                'limite_imoveis' => $tenant->planoAssinatura->limite_imoveis,
            ] : null,
            'cortesia' => $tenant->estaCortesia(),
            'imoveis_count' => $imoveisCount,
            'faturas' => $faturas,
            'planos_ativos' => $planosAtivos,
        ]);
    }

    /**
     * Altera o plano do tenant.
     */
    public function alterarPlano(Request $request): RedirectResponse
    {
        $request->validate([
            'plano_id' => ['required', 'exists:planos,id'],
        ]);

        $tenant = $this->tenantService->getTenant();
        $novoPlano = Plano::findOrFail($request->plano_id);

        // Verifica se o plano está ativo
        if ($novoPlano->status !== 'ativo') {
            return back()->withErrors(['plano_id' => 'Este plano não está disponível.']);
        }

        // Verifica se o downgrade é compatível com o uso atual
        if ($novoPlano->limite_imoveis > 0) {
            $imoveisCount = Imovel::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
            if ($imoveisCount > $novoPlano->limite_imoveis) {
                return back()->withErrors([
                    'plano_id' => "Você possui {$imoveisCount} imóveis mas o plano permite apenas {$novoPlano->limite_imoveis}. Reduza a quantidade antes de fazer downgrade.",
                ]);
            }
        }

        $tenant->update(['plano_id' => $novoPlano->id]);

        return back()->with('success', "Plano alterado para {$novoPlano->nome}!");
    }
}
