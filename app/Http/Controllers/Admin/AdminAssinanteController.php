<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Models\Imovel;
use App\Models\Plano;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAssinanteController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Tenant::withoutGlobalScopes()
            ->with('planoAssinatura')
            ->withCount('vinculos');

        if ($busca = $request->input('busca')) {
            $query->where(fn ($q) => $q
                ->where('nome', 'like', "%{$busca}%")
                ->orWhere('documento', 'like', "%{$busca}%")
            );
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($planoId = $request->input('plano_id')) {
            $query->where('plano_id', $planoId);
        }

        if ($request->input('cortesia') === 'sim') {
            $query->where('cortesia', true);
        } elseif ($request->input('cortesia') === 'nao') {
            $query->where('cortesia', false);
        }

        $assinantes = $query->orderBy('nome')->paginate(20)->withQueryString();

        // Adicionar contagem de imóveis a cada tenant
        $assinantes->getCollection()->each(function ($tenant) {
            $tenant->imoveis_count = Imovel::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        });

        $resumo = [
            'ativos' => Tenant::withoutGlobalScopes()->where('status', 'ativo')->count(),
            'cortesias' => Tenant::withoutGlobalScopes()->where('cortesia', true)->count(),
            'bloqueados' => Tenant::withoutGlobalScopes()->where('status', 'bloqueado')->count(),
            'cancelados' => Tenant::withoutGlobalScopes()->where('status', 'cancelado')->count(),
        ];

        $planos = Plano::ativo()->ordenado()->get(['id', 'nome']);

        return Inertia::render('admin/assinantes/index', [
            'assinantes' => $assinantes,
            'resumo' => $resumo,
            'planos' => $planos,
            'filtros' => [
                'busca' => $request->input('busca', ''),
                'status' => $request->input('status', ''),
                'plano_id' => $request->input('plano_id', ''),
                'cortesia' => $request->input('cortesia', ''),
            ],
        ]);
    }

    public function show(Tenant $tenant): Response
    {
        $tenant->load(['planoAssinatura', 'vinculos.user']);

        $imoveis_count = Imovel::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        $contratos_ativos = Contrato::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'ativo')->count();
        $faturas = $tenant->faturasKimobe()->orderBy('data_vencimento', 'desc')->limit(6)->get();
        $planos = Plano::ativo()->ordenado()->get();

        return Inertia::render('admin/assinantes/mostrar', [
            'tenant' => $tenant,
            'imoveis_count' => $imoveis_count,
            'contratos_ativos' => $contratos_ativos,
            'faturas' => $faturas,
            'planos' => $planos,
        ]);
    }

    public function alterarPlano(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate(['plano_id' => ['required', 'exists:planos,id']]);
        $tenant->update(['plano_id' => $request->plano_id]);

        return redirect()->route('admin.assinantes.show', $tenant)->with('success', 'Plano alterado com sucesso.');
    }

    public function toggleCortesia(Request $request, Tenant $tenant): RedirectResponse
    {
        if ($tenant->cortesia) {
            $tenant->update(['cortesia' => false, 'motivo_cortesia' => null]);
            return redirect()->route('admin.assinantes.show', $tenant)->with('success', 'Cortesia removida.');
        }

        $request->validate(['motivo_cortesia' => ['required', 'string', 'max:255']]);
        $tenant->update(['cortesia' => true, 'motivo_cortesia' => $request->motivo_cortesia]);

        return redirect()->route('admin.assinantes.show', $tenant)->with('success', 'Cortesia aplicada.');
    }

    public function suspender(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'suspenso']);
        return redirect()->route('admin.assinantes.show', $tenant)->with('success', 'Assinante suspenso.');
    }

    public function reativar(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'ativo', 'bloqueado_em' => null, 'motivo_bloqueio' => null]);
        return redirect()->route('admin.assinantes.show', $tenant)->with('success', 'Assinante reativado.');
    }

    public function cancelar(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate(['motivo' => ['required', 'string', 'max:255']]);
        $tenant->update(['status' => 'cancelado', 'motivo_bloqueio' => $request->motivo]);

        return redirect()->route('admin.assinantes.show', $tenant)->with('success', 'Assinatura cancelada.');
    }

    public function desbloquear(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'ativo', 'bloqueado_em' => null, 'motivo_bloqueio' => null]);
        return redirect()->route('admin.assinantes.show', $tenant)->with('success', 'Assinante desbloqueado.');
    }
}
