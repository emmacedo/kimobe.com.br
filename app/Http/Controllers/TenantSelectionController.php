<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantSelectionController extends Controller
{
    public function __construct(
        protected TenantService $tenantService,
    ) {}

    /**
     * Exibe a tela de seleção de contexto (tenant).
     * Se o usuário só tem 1 tenant, redireciona direto para o dashboard.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $vinculos = $this->tenantService->getUserVinculos($request->user());

        // Agrupa por tenant para montar a lista de opções com seus papéis
        $tenants = $vinculos->groupBy('tenant_id')->map(function ($vinculosTenant) {
            $tenant = $vinculosTenant->first()->tenant;

            return [
                'id' => $tenant->id,
                'nome' => $tenant->nome,
                'tipo' => $tenant->tipo,
                'papeis' => $vinculosTenant->pluck('papel')->toArray(),
            ];
        })->values();

        // Se só tem 1 tenant, redireciona direto
        if ($tenants->count() === 1) {
            $tenant = Tenant::find($tenants->first()['id']);
            $this->tenantService->setTenant($tenant);

            return redirect()->route('dashboard');
        }

        return Inertia::render('tenant/selecionar', [
            'tenants' => $tenants,
        ]);
    }

    /**
     * Salva o tenant selecionado na sessão.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer'],
        ]);

        // Verifica se o usuário tem vínculo ativo com o tenant informado
        $vinculoValido = $request->user()->vinculos()
            ->where('tenant_id', $validated['tenant_id'])
            ->where('status', 'ativo')
            ->exists();

        if (! $vinculoValido) {
            return redirect()->route('tenant.selecionar')
                ->withErrors(['tenant_id' => 'Você não possui acesso a este ambiente.']);
        }

        $tenant = Tenant::findOrFail($validated['tenant_id']);
        $this->tenantService->setTenant($tenant);

        return redirect()->route('dashboard');
    }

    /**
     * Limpa o tenant da sessão e redireciona para a tela de seleção.
     */
    public function trocar(): RedirectResponse
    {
        $this->tenantService->clearTenant();

        return redirect()->route('tenant.selecionar');
    }

    /**
     * Exibe a tela informando que o usuário não possui acesso a nenhuma empresa.
     */
    public function semAcesso(): Response
    {
        return Inertia::render('tenant/sem-acesso');
    }

    /**
     * Exibe a tela de bloqueio quando o tenant está bloqueado, suspenso ou cancelado.
     */
    public function bloqueado(): Response
    {
        $tenant = $this->tenantService->getTenant();
        $hasMultipleTenants = false;

        if ($request = request()) {
            $user = $request->user();
            if ($user) {
                $tenantCount = $user->vinculos()->where('status', 'ativo')->distinct('tenant_id')->count('tenant_id');
                $hasMultipleTenants = $tenantCount > 1;
            }
        }

        return Inertia::render('tenant/bloqueado', [
            'tenant' => $tenant ? [
                'nome' => $tenant->nome,
                'status' => $tenant->status,
                'motivo_bloqueio' => $tenant->motivo_bloqueio,
            ] : null,
            'has_multiple_tenants' => $hasMultipleTenants,
        ]);
    }
}
