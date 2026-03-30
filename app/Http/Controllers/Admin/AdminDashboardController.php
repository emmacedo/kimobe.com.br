<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessarInadimplenciaKimobe;
use App\Models\FaturaKimobe;
use App\Models\Imovel;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        $admin = Auth::guard('admin')->user();
        $mesAtual = now()->format('m/Y');
        $mesAnterior = now()->subMonth()->format('m/Y');

        $assinantesAtivos = Tenant::withoutGlobalScopes()->where('status', 'ativo')->count();

        $receitaMensal = FaturaKimobe::where('status', 'pago')
            ->where('referencia', $mesAtual)->sum('valor');
        $receitaAnterior = FaturaKimobe::where('status', 'pago')
            ->where('referencia', $mesAnterior)->sum('valor');
        $variacaoReceita = $receitaAnterior > 0
            ? round((($receitaMensal - $receitaAnterior) / $receitaAnterior) * 100, 1) : 0;

        $inadimplentes = FaturaKimobe::where('status', 'atrasado')->count();
        $valorInadimplente = FaturaKimobe::where('status', 'atrasado')->sum('valor');

        $imoveisTotal = Imovel::withoutGlobalScopes()->count();
        $imoveisNovosMes = Imovel::withoutGlobalScopes()
            ->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();

        $receita6meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $data = now()->subMonths($i);
            $ref = $data->format('m/Y');
            $receita6meses[] = [
                'mes' => $data->format('M/Y'),
                'valor' => (float) FaturaKimobe::where('status', 'pago')->where('referencia', $ref)->sum('valor'),
            ];
        }

        $assinantesRecentes = Tenant::withoutGlobalScopes()->with('planoAssinatura')
            ->orderBy('created_at', 'desc')->limit(5)->get();
        $faturasPendentes = FaturaKimobe::with('tenant')
            ->whereIn('status', ['pendente', 'atrasado'])->orderBy('data_vencimento')->limit(5)->get();

        return Inertia::render('admin/dashboard', [
            'admin' => $admin,
            'assinantes_ativos' => $assinantesAtivos,
            'receita_mensal' => $receitaMensal,
            'variacao_receita' => $variacaoReceita,
            'inadimplentes' => $inadimplentes,
            'valor_inadimplente' => $valorInadimplente,
            'imoveis_total' => $imoveisTotal,
            'imoveis_novos_mes' => $imoveisNovosMes,
            'receita_6_meses' => $receita6meses,
            'assinantes_recentes' => $assinantesRecentes,
            'faturas_pendentes' => $faturasPendentes,
        ]);
    }

    public function executarInadimplencia(): JsonResponse
    {
        $job = new ProcessarInadimplenciaKimobe;
        $job->handle();

        return response()->json($job->resultado);
    }
}
