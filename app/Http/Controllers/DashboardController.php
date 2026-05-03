<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\Imovel;
use App\Models\Repasse;
use App\Models\Scopes\TenantScope;
use App\Models\Vinculo;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected TenantService $tenantService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $tenant = $this->tenantService->getTenant();
        $papeis = $this->tenantService->getUserPapeis($user, $tenant);

        $isAdmin = in_array('admin', $papeis);
        $isProprietario = in_array('proprietario', $papeis);
        $isInquilino = in_array('inquilino', $papeis);

        $dados = [];

        // Determinar papel principal para o dashboard
        if ($isAdmin) {
            $dados = $this->dadosAdmin($papeis, $user);
            $dados['dashboard_tipo'] = 'admin';
        } elseif ($isProprietario) {
            $dados = $this->dadosProprietario($user, $tenant);
            $dados['dashboard_tipo'] = 'proprietario';
        } else {
            $dados = $this->dadosInquilino($user, $tenant);
            $dados['dashboard_tipo'] = 'inquilino';
        }

        $dados['papeis'] = $papeis;

        return Inertia::render('dashboard', $dados);
    }

    private function dadosAdmin(array $papeis, $user): array
    {
        $mesAtual = now()->format('m/Y');

        $receitaMensal = Fatura::where('status', 'pago')
            ->where('referencia', $mesAtual)
            ->sum('valor_pago');

        $totalImoveis = Imovel::count();
        $imoveisAlugados = Imovel::where('status', 'alugado')->count();
        $taxaOcupacao = $totalImoveis > 0 ? round(($imoveisAlugados / $totalImoveis) * 100, 1) : 0;

        $totalCobrancasMes = Fatura::where('referencia', $mesAtual)->count();
        $cobrancasAtrasadas = Fatura::where('status', 'atrasado')->count();
        $inadimplencia = $totalCobrancasMes > 0 ? round(($cobrancasAtrasadas / $totalCobrancasMes) * 100, 1) : 0;

        $contratosAtivos = Contrato::where('status', 'ativo')->count();

        // Últimas 10 movimentações
        $ultimasMovimentacoes = Fatura::with(['contrato.imovel', 'contrato.inquilino.user'])
            ->orderBy('data_vencimento', 'desc')
            ->limit(10)
            ->get();

        // Pendências para barra de contexto
        $cobrancasPendentes = Fatura::whereIn('status', ['pendente', 'atrasado'])->count();

        $dados = [
            'receita_mensal' => $receitaMensal,
            'taxa_ocupacao' => $taxaOcupacao,
            'imoveis_alugados' => $imoveisAlugados,
            'total_imoveis' => $totalImoveis,
            'inadimplencia' => $inadimplencia,
            'cobrancas_atrasadas' => $cobrancasAtrasadas,
            'contratos_ativos' => $contratosAtivos,
            'ultimas_movimentacoes' => $ultimasMovimentacoes,
            'cobrancas_pendentes' => $cobrancasPendentes,
        ];

        // Se também é proprietário, adicionar resumo de rendimentos
        if (in_array('proprietario', $papeis)) {
            $vinculoIds = Vinculo::where('user_id', $user->id)
                ->where('papel', 'proprietario')
                ->pluck('id');

            $rendimentoMes = Repasse::withoutGlobalScopes()
                ->where('status', 'realizado')
                ->whereHas('titularidade', fn ($q) => $q->withoutGlobalScopes([TenantScope::class])->whereIn('vinculo_id', $vinculoIds))
                ->whereMonth('data_realizada', now()->month)
                ->whereYear('data_realizada', now()->year)
                ->sum('valor_liquido');

            $dados['rendimento_proprietario_mes'] = $rendimentoMes;
        }

        return $dados;
    }

    private function dadosProprietario($user, $tenant): array
    {
        $vinculoIds = Vinculo::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->where('papel', 'proprietario')
            ->pluck('id');

        // Repasses realizados no mês
        $receitaMes = Repasse::withoutGlobalScopes()
            ->where('status', 'realizado')
            ->whereHas('titularidade', fn ($q) => $q->withoutGlobalScopes([TenantScope::class])->whereIn('vinculo_id', $vinculoIds))
            ->whereMonth('data_realizada', now()->month)
            ->whereYear('data_realizada', now()->year)
            ->sum('valor_liquido');

        // Repasses pendentes
        $repassesPendentes = Repasse::withoutGlobalScopes()
            ->where('status', 'pendente')
            ->whereHas('titularidade', fn ($q) => $q->withoutGlobalScopes([TenantScope::class])->whereIn('vinculo_id', $vinculoIds));

        $pendentesCount = (clone $repassesPendentes)->count();
        $pendentesValor = (clone $repassesPendentes)->sum('valor_liquido');

        // Count imóveis onde é titular
        $meusImoveis = Imovel::whereHas('titularidades', fn ($q) => $q->whereIn('vinculo_id', $vinculoIds))->count();

        // Últimos 10 repasses
        $ultimosRepasses = Repasse::withoutGlobalScopes()
            ->whereHas('titularidade', fn ($q) => $q->withoutGlobalScopes([TenantScope::class])->whereIn('vinculo_id', $vinculoIds))
            ->with(['fatura.contrato.imovel', 'titularidade.vinculo.user'])
            ->orderBy('data_prevista', 'desc')
            ->limit(10)
            ->get();

        return [
            'receita_mes' => $receitaMes,
            'pendentes_count' => $pendentesCount,
            'pendentes_valor' => $pendentesValor,
            'meus_imoveis' => $meusImoveis,
            'ultimos_repasses' => $ultimosRepasses,
        ];
    }

    private function dadosInquilino($user, $tenant): array
    {
        $vinculoIds = Vinculo::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->where('papel', 'inquilino')
            ->pluck('id');

        $contratoIds = Contrato::whereIn('inquilino_vinculo_id', $vinculoIds)->pluck('id');

        // Próxima cobrança pendente
        $proximaCobranca = Fatura::whereIn('contrato_id', $contratoIds)
            ->whereIn('status', ['pendente', 'atrasado'])
            ->with(['contrato.imovel'])
            ->orderBy('data_vencimento')
            ->first();

        // Total pago no ano
        $totalPagoAno = Fatura::whereIn('contrato_id', $contratoIds)
            ->where('status', 'pago')
            ->whereYear('data_pagamento', now()->year)
            ->sum('valor_pago');

        // % em dia
        $totalPagas = Fatura::whereIn('contrato_id', $contratoIds)
            ->where('status', 'pago')
            ->whereYear('data_pagamento', now()->year)
            ->count();
        $pagasEmDia = Fatura::whereIn('contrato_id', $contratoIds)
            ->where('status', 'pago')
            ->whereYear('data_pagamento', now()->year)
            ->whereColumn('data_pagamento', '<=', 'data_vencimento')
            ->count();
        $percEmDia = $totalPagas > 0 ? round(($pagasEmDia / $totalPagas) * 100, 0) : 100;

        // Últimas 10 cobranças
        $ultimasCobrancas = Fatura::whereIn('contrato_id', $contratoIds)
            ->with(['contrato.imovel'])
            ->orderBy('data_vencimento', 'desc')
            ->limit(10)
            ->get();

        return [
            'proxima_cobranca' => $proximaCobranca,
            'total_pago_ano' => $totalPagoAno,
            'perc_em_dia' => $percEmDia,
            'ultimas_cobrancas' => $ultimasCobrancas,
        ];
    }
}
