<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Models\Imovel;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Kicol\FullFlow\Models\FullFlowPlan;

class AdminAssinanteController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Tenant::withoutGlobalScopes()
            ->with('fullflowSubscription')
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

        $planoFiltro = $request->input('plano_id') ?: $request->input('plano_code');
        if ($planoFiltro) {
            $query->whereHas('fullflowSubscription', fn ($q) => $q->where('plan_code', $planoFiltro));
        }

        if ($request->input('cortesia') === 'sim') {
            $query->where('is_exempt_from_subscription', true);
        } elseif ($request->input('cortesia') === 'nao') {
            $query->where('is_exempt_from_subscription', false);
        }

        $assinantes = $query->orderBy('nome')->paginate(20)->withQueryString();

        $planosCatalogo = FullFlowPlan::with('modules')->get()->keyBy('code');

        $assinantes->getCollection()->each(function ($tenant) use ($planosCatalogo) {
            $tenant->imoveis_count = Imovel::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
            $tenant->cortesia = (bool) $tenant->is_exempt_from_subscription;
            $tenant->motivo_cortesia = $tenant->motivo_isencao;

            $planoCode = $tenant->fullflowSubscription?->plan_code;
            $plano = $planoCode ? $planosCatalogo->get($planoCode) : null;
            $quota = $plano?->modules->firstWhere('slug', 'imoveis')?->pivot?->quota_value;

            $tenant->plano_assinatura = $plano ? [
                'nome' => $plano->name,
                'limite_imoveis' => $quota === null ? 0 : (int) $quota,
            ] : null;
        });

        $resumo = [
            'ativos' => Tenant::withoutGlobalScopes()->where('status', 'ativo')->count(),
            'cortesias' => Tenant::withoutGlobalScopes()->where('is_exempt_from_subscription', true)->count(),
            'bloqueados' => Tenant::withoutGlobalScopes()->where('status', 'bloqueado')->count(),
            'cancelados' => Tenant::withoutGlobalScopes()->where('status', 'cancelado')->count(),
        ];

        $planos = FullFlowPlan::orderBy('sort_order')->get()->map(fn ($p) => [
            'id' => $p->code,
            'nome' => $p->name,
        ]);

        return Inertia::render('admin/assinantes/index', [
            'assinantes' => $assinantes,
            'resumo' => $resumo,
            'planos' => $planos,
            'filtros' => [
                'busca' => $request->input('busca', ''),
                'status' => $request->input('status', ''),
                'plano_id' => $planoFiltro ?: '',
                'cortesia' => $request->input('cortesia', ''),
            ],
        ]);
    }

    public function show(Tenant $tenant): Response
    {
        $tenant->load(['fullflowSubscription', 'vinculos.user']);

        $imoveis_count = Imovel::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        $contratos_ativos = Contrato::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'ativo')->count();
        $planos = FullFlowPlan::with('modules')->orderBy('sort_order')->get();
        $planoAtual = $tenant->currentFullFlowPlan();

        return Inertia::render('admin/assinantes/mostrar', [
            'tenant' => $tenant,
            'imoveis_count' => $imoveis_count,
            'contratos_ativos' => $contratos_ativos,
            'plano_atual' => $planoAtual,
            'planos' => $planos,
        ]);
    }

    public function toggleCortesia(Request $request, Tenant $tenant): RedirectResponse
    {
        if ($tenant->is_exempt_from_subscription) {
            $tenant->update(['is_exempt_from_subscription' => false, 'motivo_isencao' => null]);

            return redirect()->route('admin.assinantes.show', $tenant)->with('success', 'Cortesia removida.');
        }

        $request->validate(['motivo_isencao' => ['required', 'string', 'max:255']]);
        $tenant->update(['is_exempt_from_subscription' => true, 'motivo_isencao' => $request->motivo_isencao]);

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
