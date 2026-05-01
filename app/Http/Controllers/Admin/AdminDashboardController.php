<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FullFlowSubscription;
use App\Models\Imovel;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Kicol\FullFlow\Models\FullFlowPlan;

/**
 * Dashboard admin do Kimobe — KPIs vêm do catálogo central FullFlow.
 *
 * Receita real é cobrança via Asaas (FullFlow), faturas internas
 * (FaturaKimobe) foram descontinuadas. Para extratos financeiros use
 * o painel do FullFlow.
 */
class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        $admin = Auth::guard('admin')->user();

        $assinantesAtivos = Tenant::withoutGlobalScopes()->where('status', 'ativo')->count();
        $cortesias = Tenant::withoutGlobalScopes()->where('is_exempt_from_subscription', true)->count();

        $assinaturasAtivas = FullFlowSubscription::whereIn('status', ['trial', 'ativa', 'past_due', 'cancelamento_agendado'])->count();

        $imoveisTotal = Imovel::withoutGlobalScopes()->count();
        $imoveisNovosMes = Imovel::withoutGlobalScopes()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $totalPlanos = FullFlowPlan::count();

        // Últimos assinantes com plano resolvido via plan_code (sem N+1)
        $assinantesRecentes = Tenant::withoutGlobalScopes()
            ->with('fullflowSubscription')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
        $planNamesByCode = FullFlowPlan::pluck('name', 'code')->all();

        return Inertia::render('admin/dashboard', [
            'admin' => $admin,
            'assinantes_ativos' => $assinantesAtivos,
            'assinaturas_ativas' => $assinaturasAtivas,
            'cortesias' => $cortesias,
            'imoveis_total' => $imoveisTotal,
            'imoveis_novos_mes' => $imoveisNovosMes,
            'total_planos' => $totalPlanos,
            'assinantes_recentes' => $assinantesRecentes,
            'plan_names_by_code' => $planNamesByCode,
        ]);
    }
}
