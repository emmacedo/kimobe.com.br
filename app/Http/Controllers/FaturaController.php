<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Fatura;
use App\Services\FaturaService;
use App\Services\NotificacaoAdminService;
use App\Traits\ScopesPorPapel;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class FaturaController extends Controller
{
    use ScopesPorPapel;

    public function __construct(
        protected FaturaService $faturaService,
    ) {}

    /**
     * Listagem de faturas com filtros por período, paginação e cards de resumo.
     */
    public function index(Request $request): Response
    {
        $mesAno = $request->input('mes', now()->format('Y-m'));
        $partes = explode('-', $mesAno);
        $referenciaFiltro = str_pad($partes[1] ?? now()->month, 2, '0', STR_PAD_LEFT).'/'.($partes[0] ?? now()->year);

        $query = Fatura::query()
            ->with(['contrato.imovel', 'contrato.inquilino.user']);

        $this->scopeFaturasDoUsuario($query);

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

        $faturas = $query->orderBy('data_vencimento', 'desc')
            ->paginate(20)
            ->withQueryString();

        $baseQuery = Fatura::where('referencia', $referenciaFiltro);
        $this->scopeFaturasDoUsuario($baseQuery);
        $resumo = [
            'a_receber' => (clone $baseQuery)->where('status', 'pendente')->sum('valor_total'),
            'a_receber_count' => (clone $baseQuery)->where('status', 'pendente')->count(),
            'recebido' => (clone $baseQuery)->where('status', 'pago')->sum('valor_pago'),
            'recebido_count' => (clone $baseQuery)->where('status', 'pago')->count(),
            'em_atraso' => $this->scopeFaturasDoUsuario(Fatura::where('status', 'atrasado'))->sum('valor_total'),
            'em_atraso_count' => $this->scopeFaturasDoUsuario(Fatura::where('status', 'atrasado'))->count(),
            'canceladas' => (clone $baseQuery)->where('status', 'cancelado')->sum('valor_total'),
            'canceladas_count' => (clone $baseQuery)->where('status', 'cancelado')->count(),
        ];

        return Inertia::render('financeiro/faturas/index', [
            'faturas' => $faturas,
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
     * Formulário de criação manual de fatura.
     */
    public function create(): Response
    {
        $contratos = Contrato::where('status', 'ativo')
            ->with(['imovel', 'inquilino.user'])
            ->get();

        return Inertia::render('financeiro/faturas/criar', [
            'contratos' => $contratos,
        ]);
    }

    /**
     * Salva uma fatura criada manualmente. Cria fatura vazia — itens são
     * adicionados via tela de detalhes (gestão de itens vem no item 5).
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'contrato_id' => ['required', 'integer'],
            'referencia' => ['required', 'string', 'regex:/^\d{2}\/\d{4}$/'],
            'data_vencimento' => ['required', 'date'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ]);

        $contrato = Contrato::findOrFail($request->contrato_id);

        $existe = Fatura::where('contrato_id', $contrato->id)
            ->where('referencia', $request->referencia)
            ->exists();

        if ($existe) {
            return back()->withErrors(['referencia' => 'Já existe uma fatura para este contrato nesta referência.']);
        }

        $fatura = $this->faturaService->gerarFaturaIndividual($contrato, $request->referencia, 'manual');

        if ($request->observacoes || $request->data_vencimento) {
            $fatura->update([
                'observacoes' => $request->observacoes,
                'data_vencimento' => $request->data_vencimento,
            ]);
        }

        return redirect()->route('faturas.show', $fatura)
            ->with('success', 'Fatura criada com sucesso.');
    }

    /**
     * Preview dos contratos que teriam fatura gerada em um mês.
     */
    public function previewMensais(Request $request): JsonResponse
    {
        $referencia = $request->input('referencia');

        $contratos = Contrato::where('status', 'ativo')
            ->whereDoesntHave('faturas', fn ($q) => $q->where('referencia', $referencia))
            ->with(['imovel', 'inquilino.user'])
            ->get()
            ->map(fn ($contrato) => [
                'id' => $contrato->id,
                'imovel' => $contrato->imovel->complemento ?? "{$contrato->imovel->logradouro}, {$contrato->imovel->numero}",
                'inquilino' => $contrato->inquilino->user->name,
                'valor_aluguel' => $contrato->valor_aluguel,
            ]);

        $jaComFatura = Contrato::where('status', 'ativo')
            ->whereHas('faturas', fn ($q) => $q->where('referencia', $referencia))
            ->count();

        return response()->json([
            'contratos' => $contratos,
            'ja_com_fatura' => $jaComFatura,
        ]);
    }

    /**
     * Gera faturas mensais em batch para todos os contratos ativos.
     */
    public function gerarMensais(Request $request): JsonResponse
    {
        $request->validate([
            'referencia' => ['required', 'string', 'regex:/^\d{2}\/\d{4}$/'],
        ]);

        $resultado = $this->faturaService->gerarFaturasMensais($request->referencia);

        return response()->json($resultado);
    }

    /**
     * Detalhes de uma fatura.
     */
    public function show(Fatura $fatura): Response
    {
        $fatura->load(['contrato.imovel', 'contrato.inquilino']);
        abort_unless($this->podeVerFatura($fatura), 403);

        $fatura->load([
            'contrato.imovel',
            'contrato.inquilino.user',
            'itens',
            'comprovantes',
            'repasses.titularidade.vinculo.user',
        ]);

        $acrescimos = null;
        if (in_array($fatura->status, ['atrasado', 'pendente']) && $fatura->data_vencimento->isPast()) {
            $diasCarencia = $fatura->contrato->dias_carencia ?? 0;
            $diasAtraso = max(0, (int) Carbon::today()->diffInDays($fatura->data_vencimento) - $diasCarencia);

            $valorTotal = (float) $fatura->valor_total;
            $multaPct = (float) $fatura->contrato->multa_atraso_pct;
            $jurosPctDia = (float) $fatura->contrato->juros_atraso_pct_dia;

            $multaEstimada = round($valorTotal * $multaPct / 100, 2);
            $jurosEstimados = round($valorTotal * $jurosPctDia / 100 * $diasAtraso, 2);

            $acrescimos = [
                'dias_atraso' => $diasAtraso,
                'multa_estimada' => $multaEstimada,
                'juros_estimados' => $jurosEstimados,
                'total_estimado' => round($valorTotal + $multaEstimada + $jurosEstimados, 2),
            ];
        }

        return Inertia::render('financeiro/faturas/mostrar', [
            'fatura' => $fatura,
            'acrescimos' => $acrescimos,
        ]);
    }

    /**
     * Registra o pagamento (baixa) de uma fatura.
     * Gera repasses para modelo por_recebimento.
     */
    public function registrarPagamento(Request $request, Fatura $fatura): RedirectResponse
    {
        if (! in_array($fatura->status, ['pendente', 'atrasado'])) {
            return back()->withErrors(['fatura' => 'Apenas faturas pendentes ou atrasadas podem receber pagamento.']);
        }

        $request->validate([
            'data_pagamento' => ['required', 'date'],
            'metodo_pagamento' => ['required', 'in:boleto,pix,transferencia,dinheiro'],
            'valor_pago' => ['required', 'numeric', 'min:0.01'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ]);

        $contrato = $fatura->contrato;
        $dataPgto = Carbon::parse($request->data_pagamento);
        $dataVenc = $fatura->data_vencimento;
        $valorTotal = (float) $fatura->valor_total;

        // Usa o snapshot da fatura (não os valores atuais do contrato) — preserva
        // o que estava vigente quando a fatura foi gerada.
        $diasCarencia = $fatura->dias_carencia_aplicada ?? $contrato->dias_carencia ?? 0;
        $multaPct = $fatura->multa_atraso_pct_aplicada ?? $contrato->multa_atraso_pct;
        $jurosPct = $fatura->juros_atraso_pct_dia_aplicada ?? $contrato->juros_atraso_pct_dia;
        $descontoPct = $fatura->desconto_pontualidade_pct_aplicada ?? $contrato->desconto_pontualidade_pct;

        $desconto = 0;
        $multa = 0;
        $juros = 0;

        if ($dataPgto->lte($dataVenc) && $descontoPct) {
            $desconto = round($valorTotal * (float) $descontoPct / 100, 2);
        } elseif ($dataPgto->gt($dataVenc->copy()->addDays($diasCarencia))) {
            $diasAtraso = (int) $dataPgto->diffInDays($dataVenc) - $diasCarencia;
            $multa = round($valorTotal * (float) $multaPct / 100, 2);
            $juros = round($valorTotal * (float) $jurosPct / 100 * $diasAtraso, 2);
        }

        $fatura->update([
            'data_pagamento' => $request->data_pagamento,
            'metodo_pagamento' => $request->metodo_pagamento,
            'valor_pago' => $request->valor_pago,
            'valor_desconto' => $desconto > 0 ? $desconto : null,
            'valor_multa' => $multa > 0 ? $multa : null,
            'valor_juros' => $juros > 0 ? $juros : null,
            'status' => 'pago',
            'observacoes' => $request->observacoes ?? $fatura->observacoes,
            'baixada_por_user_id' => auth()->id(),
        ]);

        $repassesGerados = 0;
        if ($contrato->modelo_repasse === 'por_recebimento') {
            $this->faturaService->gerarRepasses($fatura, $contrato, $request->data_pagamento);
            $repassesGerados = $fatura->repasses()->count();
        }

        try {
            app(NotificacaoAdminService::class)->notificarPagamentoInquilino($fatura->fresh());
        } catch (\Throwable $e) {
            Log::warning("Falha ao notificar pagamento: {$e->getMessage()}");
        }

        $msg = 'Pagamento registrado com sucesso.';
        if ($repassesGerados > 0) {
            $msg .= " {$repassesGerados} repasse(s) gerado(s).";
        }

        return redirect()->route('faturas.show', $fatura)->with('success', $msg);
    }

    /**
     * Cancela uma fatura pendente ou atrasada.
     */
    public function cancelar(Fatura $fatura): RedirectResponse
    {
        if (! in_array($fatura->status, ['pendente', 'atrasado'])) {
            return back()->withErrors(['fatura' => 'Apenas faturas pendentes ou atrasadas podem ser canceladas.']);
        }

        $fatura->update(['status' => 'cancelado']);
        $fatura->repasses()->where('status', 'pendente')->update(['status' => 'cancelado']);

        return redirect()->route('faturas.show', $fatura)
            ->with('success', 'Fatura cancelada com sucesso.');
    }
}
