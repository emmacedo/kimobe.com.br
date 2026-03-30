<?php

namespace App\Http\Controllers;

use App\Models\Cobranca;
use App\Models\Contrato;
use App\Services\CobrancaService;
use App\Services\TenantService;
use App\Traits\ScopesPorPapel;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CobrancaController extends Controller
{
    use ScopesPorPapel;

    public function __construct(
        protected CobrancaService $cobrancaService,
    ) {}

    /**
     * Listagem de cobranças com filtros por período, paginação e cards de resumo.
     */
    public function index(Request $request): Response
    {
        $mesAno = $request->input('mes', now()->format('Y-m'));
        $partes = explode('-', $mesAno);
        $referenciaFiltro = str_pad($partes[1] ?? now()->month, 2, '0', STR_PAD_LEFT) . '/' . ($partes[0] ?? now()->year);

        $query = Cobranca::query()
            ->with(['contrato.imovel', 'contrato.inquilino.user']);

        // Scoping por papel
        $this->scopeCobrancasDoUsuario($query);

        $query->where('referencia', $referenciaFiltro);

        if ($busca = $request->input('busca')) {
            $query->whereHas('contrato', function ($qc) use ($busca) {
                $qc->whereHas('imovel', fn ($qi) => $qi
                    ->where('logradouro', 'like', "%{$busca}%")
                    ->orWhere('complemento', 'like', "%{$busca}%")
                )->orWhereHas('inquilino.user', fn ($qu) => $qu
                    ->where('name', 'like', "%{$busca}%")
                );
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($metodo = $request->input('metodo_pagamento')) {
            $query->where('metodo_pagamento', $metodo);
        }

        $cobrancas = $query->orderBy('data_vencimento', 'desc')
            ->paginate(20)
            ->withQueryString();

        $baseQuery = Cobranca::where('referencia', $referenciaFiltro);
        $this->scopeCobrancasDoUsuario($baseQuery);
        $resumo = [
            'a_receber' => (clone $baseQuery)->where('status', 'pendente')->sum('valor_total'),
            'a_receber_count' => (clone $baseQuery)->where('status', 'pendente')->count(),
            'recebido' => (clone $baseQuery)->where('status', 'pago')->sum('valor_pago'),
            'recebido_count' => (clone $baseQuery)->where('status', 'pago')->count(),
            'em_atraso' => $this->scopeCobrancasDoUsuario(Cobranca::where('status', 'atrasado'))->sum('valor_total'),
            'em_atraso_count' => $this->scopeCobrancasDoUsuario(Cobranca::where('status', 'atrasado'))->count(),
            'canceladas' => (clone $baseQuery)->where('status', 'cancelado')->sum('valor_total'),
            'canceladas_count' => (clone $baseQuery)->where('status', 'cancelado')->count(),
        ];

        return Inertia::render('financeiro/cobrancas/index', [
            'cobrancas' => $cobrancas,
            'resumo' => $resumo,
            'filtros' => [
                'mes' => $mesAno,
                'busca' => $request->input('busca', ''),
                'status' => $request->input('status', ''),
                'metodo_pagamento' => $request->input('metodo_pagamento', ''),
            ],
        ]);
    }

    /**
     * Formulário de criação manual de cobrança.
     */
    public function create(): Response
    {
        $contratos = Contrato::where('status', 'ativo')
            ->with(['imovel', 'inquilino.user', 'responsabilidades'])
            ->get();

        return Inertia::render('financeiro/cobrancas/criar', [
            'contratos' => $contratos,
        ]);
    }

    /**
     * Salva uma cobrança criada manualmente.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'contrato_id' => ['required', 'integer'],
            'referencia' => ['required', 'string', 'regex:/^\d{2}\/\d{4}$/'],
            'valor_aluguel' => ['required', 'numeric', 'min:0'],
            'valor_condominio' => ['nullable', 'numeric', 'min:0'],
            'valor_iptu' => ['nullable', 'numeric', 'min:0'],
            'valor_seguro_incendio' => ['nullable', 'numeric', 'min:0'],
            'valor_taxa_bombeiros' => ['nullable', 'numeric', 'min:0'],
            'valor_taxa_extra_condominio' => ['nullable', 'numeric', 'min:0'],
            'data_vencimento' => ['required', 'date'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ]);

        $contrato = Contrato::findOrFail($request->contrato_id);

        // Verificar duplicidade
        $existe = Cobranca::where('contrato_id', $contrato->id)
            ->where('referencia', $request->referencia)
            ->exists();

        if ($existe) {
            return back()->withErrors(['referencia' => 'Já existe uma cobrança para este contrato nesta referência.']);
        }

        $overrides = $request->only([
            'valor_aluguel', 'valor_condominio', 'valor_iptu',
            'valor_seguro_incendio', 'valor_taxa_bombeiros', 'valor_taxa_extra_condominio',
        ]);

        $cobranca = $this->cobrancaService->gerarCobrancaIndividual(
            $contrato,
            $request->referencia,
            array_filter($overrides, fn ($v) => $v !== null),
        );

        if ($request->observacoes) {
            $cobranca->update(['observacoes' => $request->observacoes, 'tipo_geracao' => 'manual']);
        }

        return redirect()->route('cobrancas.show', $cobranca)
            ->with('success', 'Cobrança criada com sucesso.');
    }

    /**
     * Preview dos contratos que teriam cobrança gerada em um mês.
     */
    public function previewMensais(Request $request): JsonResponse
    {
        $referencia = $request->input('referencia');

        $contratos = Contrato::where('status', 'ativo')
            ->whereDoesntHave('cobrancas', fn ($q) => $q->where('referencia', $referencia))
            ->with(['imovel', 'inquilino.user', 'responsabilidades'])
            ->get()
            ->map(function ($contrato) {
                $somaInquilino = $contrato->responsabilidades
                    ->where('responsavel', 'inquilino')
                    ->sum(fn ($r) => $r->valor ? (float) $r->valor : 0);

                return [
                    'id' => $contrato->id,
                    'imovel' => $contrato->imovel->complemento ?? "{$contrato->imovel->logradouro}, {$contrato->imovel->numero}",
                    'inquilino' => $contrato->inquilino->user->name,
                    'valor_aluguel' => $contrato->valor_aluguel,
                    'responsabilidades' => $somaInquilino,
                    'total_estimado' => (float) $contrato->valor_aluguel + $somaInquilino,
                ];
            });

        $jaComCobranca = Contrato::where('status', 'ativo')
            ->whereHas('cobrancas', fn ($q) => $q->where('referencia', $referencia))
            ->count();

        return response()->json([
            'contratos' => $contratos,
            'ja_com_cobranca' => $jaComCobranca,
        ]);
    }

    /**
     * Gera cobranças mensais em batch para todos os contratos ativos.
     */
    public function gerarMensais(Request $request): JsonResponse
    {
        $request->validate([
            'referencia' => ['required', 'string', 'regex:/^\d{2}\/\d{4}$/'],
        ]);

        $resultado = $this->cobrancaService->gerarCobrancasMensais($request->referencia);

        return response()->json($resultado);
    }

    /**
     * Detalhes de uma cobrança.
     */
    public function show(Cobranca $cobranca): Response
    {
        $cobranca->load(['contrato.imovel', 'contrato.inquilino']);
        abort_unless($this->podeVerCobranca($cobranca), 403);

        $cobranca->load([
            'contrato.imovel',
            'contrato.inquilino.user',
            'itensExtras',
            'comprovantes',
            'repasses.titularidade.vinculo.user',
        ]);

        $acrescimos = null;
        if (in_array($cobranca->status, ['atrasado', 'pendente']) && $cobranca->data_vencimento->isPast()) {
            $diasCarencia = $cobranca->contrato->dias_carencia ?? 0;
            $diasAtraso = max(0, (int) Carbon::today()->diffInDays($cobranca->data_vencimento) - $diasCarencia);

            $valorTotal = (float) $cobranca->valor_total;
            $multaPct = (float) $cobranca->contrato->multa_atraso_pct;
            $jurosPctDia = (float) $cobranca->contrato->juros_atraso_pct_dia;

            $multaEstimada = round($valorTotal * $multaPct / 100, 2);
            $jurosEstimados = round($valorTotal * $jurosPctDia / 100 * $diasAtraso, 2);

            $acrescimos = [
                'dias_atraso' => $diasAtraso,
                'multa_estimada' => $multaEstimada,
                'juros_estimados' => $jurosEstimados,
                'total_estimado' => round($valorTotal + $multaEstimada + $jurosEstimados, 2),
            ];
        }

        return Inertia::render('financeiro/cobrancas/mostrar', [
            'cobranca' => $cobranca,
            'acrescimos' => $acrescimos,
        ]);
    }

    /**
     * Registra o pagamento (baixa) de uma cobrança.
     * Gera repasses para modelo por_recebimento.
     */
    public function registrarPagamento(Request $request, Cobranca $cobranca): RedirectResponse
    {
        if (! in_array($cobranca->status, ['pendente', 'atrasado'])) {
            return back()->withErrors(['cobranca' => 'Apenas cobranças pendentes ou atrasadas podem receber pagamento.']);
        }

        $request->validate([
            'data_pagamento' => ['required', 'date'],
            'metodo_pagamento' => ['required', 'in:boleto,pix,transferencia,dinheiro'],
            'valor_pago' => ['required', 'numeric', 'min:0.01'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ]);

        $contrato = $cobranca->contrato;
        $dataPgto = Carbon::parse($request->data_pagamento);
        $dataVenc = $cobranca->data_vencimento;
        $diasCarencia = $contrato->dias_carencia ?? 0;
        $valorTotal = (float) $cobranca->valor_total;

        $desconto = 0;
        $multa = 0;
        $juros = 0;

        if ($dataPgto->lte($dataVenc) && $contrato->desconto_pontualidade_pct) {
            // Pagou no prazo — aplicar desconto
            $desconto = round($valorTotal * (float) $contrato->desconto_pontualidade_pct / 100, 2);
        } elseif ($dataPgto->gt($dataVenc->copy()->addDays($diasCarencia))) {
            // Pagou após carência — aplicar multa e juros
            $diasAtraso = (int) $dataPgto->diffInDays($dataVenc) - $diasCarencia;
            $multa = round($valorTotal * (float) $contrato->multa_atraso_pct / 100, 2);
            $juros = round($valorTotal * (float) $contrato->juros_atraso_pct_dia / 100 * $diasAtraso, 2);
        }

        $cobranca->update([
            'data_pagamento' => $request->data_pagamento,
            'metodo_pagamento' => $request->metodo_pagamento,
            'valor_pago' => $request->valor_pago,
            'valor_desconto' => $desconto > 0 ? $desconto : null,
            'valor_multa' => $multa > 0 ? $multa : null,
            'valor_juros' => $juros > 0 ? $juros : null,
            'status' => 'pago',
            'observacoes' => $request->observacoes ?? $cobranca->observacoes,
        ]);

        // Gerar repasses para modelo por_recebimento
        $repassesGerados = 0;
        if ($contrato->modelo_repasse === 'por_recebimento') {
            $this->cobrancaService->gerarRepasses($cobranca, $contrato, $request->data_pagamento);
            $repassesGerados = $cobranca->repasses()->count();
        }

        // Notificar inquilino sobre pagamento registrado
        try {
            app(\App\Services\NotificacaoAdminService::class)->notificarPagamentoInquilino($cobranca->fresh());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Falha ao notificar pagamento: {$e->getMessage()}");
        }

        $msg = 'Pagamento registrado com sucesso.';
        if ($repassesGerados > 0) {
            $msg .= " {$repassesGerados} repasse(s) gerado(s).";
        }

        return redirect()->route('cobrancas.show', $cobranca)->with('success', $msg);
    }

    /**
     * Cancela uma cobrança pendente ou atrasada.
     */
    public function cancelar(Cobranca $cobranca): RedirectResponse
    {
        if (! in_array($cobranca->status, ['pendente', 'atrasado'])) {
            return back()->withErrors(['cobranca' => 'Apenas cobranças pendentes ou atrasadas podem ser canceladas.']);
        }

        $cobranca->update(['status' => 'cancelado']);
        $cobranca->repasses()->where('status', 'pendente')->update(['status' => 'cancelado']);

        return redirect()->route('cobrancas.show', $cobranca)
            ->with('success', 'Cobrança cancelada com sucesso.');
    }
}
