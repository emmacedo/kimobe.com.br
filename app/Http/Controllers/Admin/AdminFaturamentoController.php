<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaturaKimobe;
use App\Models\Tenant;
use App\Services\FaturamentoKimobeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFaturamentoController extends Controller
{
    public function __construct(
        protected FaturamentoKimobeService $service,
    ) {}

    public function index(Request $request): Response
    {
        $mesAno = $request->input('mes', now()->format('Y-m'));
        $partes = explode('-', $mesAno);
        $referenciaFiltro = str_pad($partes[1] ?? now()->month, 2, '0', STR_PAD_LEFT) . '/' . ($partes[0] ?? now()->year);

        $query = FaturaKimobe::with(['tenant', 'plano'])->where('referencia', $referenciaFiltro);

        if ($busca = $request->input('busca')) {
            $query->whereHas('tenant', fn ($q) => $q->where('nome', 'like', "%{$busca}%"));
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $faturas = $query->orderBy('data_vencimento', 'desc')->paginate(20)->withQueryString();

        $baseQuery = FaturaKimobe::where('referencia', $referenciaFiltro);
        $resumo = [
            'a_receber' => (clone $baseQuery)->where('status', 'pendente')->sum('valor'),
            'a_receber_count' => (clone $baseQuery)->where('status', 'pendente')->count(),
            'recebido' => (clone $baseQuery)->where('status', 'pago')->sum('valor'),
            'recebido_count' => (clone $baseQuery)->where('status', 'pago')->count(),
            'inadimplentes' => FaturaKimobe::where('status', 'atrasado')->count(),
            'cortesias' => Tenant::withoutGlobalScopes()->where('cortesia', true)->count(),
        ];

        return Inertia::render('admin/faturamento/index', [
            'faturas' => $faturas,
            'resumo' => $resumo,
            'filtros' => ['mes' => $mesAno, 'busca' => $request->input('busca', ''), 'status' => $request->input('status', '')],
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $referencia = $request->input('referencia');
        $preview = $this->service->previewFaturas($referencia);

        return response()->json(['tenants' => $preview]);
    }

    public function gerar(Request $request): JsonResponse
    {
        $request->validate(['referencia' => ['required', 'string', 'regex:/^\d{2}\/\d{4}$/']]);
        $resultado = $this->service->gerarFaturasMensais($request->referencia);

        return response()->json($resultado);
    }

    public function registrarPagamento(Request $request, FaturaKimobe $fatura): RedirectResponse
    {
        if (! in_array($fatura->status, ['pendente', 'atrasado'])) {
            return back()->withErrors(['fatura' => 'Apenas faturas pendentes ou atrasadas podem receber pagamento.']);
        }

        $request->validate([
            'data_pagamento' => ['required', 'date'],
            'metodo_pagamento' => ['required', 'in:pix,boleto,cartao,transferencia'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ]);

        $fatura->update([
            'status' => 'pago',
            'data_pagamento' => $request->data_pagamento,
            'metodo_pagamento' => $request->metodo_pagamento,
            'observacoes' => $request->observacoes,
        ]);

        // Enviar email de confirmação de pagamento
        $tenant = $fatura->tenant;
        if ($tenant) {
            $admin = \App\Models\Vinculo::where('tenant_id', $tenant->id)->where('papel', 'admin')->where('status', 'ativo')->with('user')->first()?->user;
            if ($admin) {
                app(\App\Services\EmailNotificationService::class)->enviar('kimobe.confirmacao_pagamento', $admin->email, $admin->name, [
                    'nome' => $admin->name, 'nome_empresa' => $tenant->nome,
                    'referencia' => $fatura->referencia, 'valor' => number_format($fatura->valor, 2, ',', '.'),
                    'data_pagamento' => $request->data_pagamento, 'metodo_pagamento' => $request->metodo_pagamento,
                ], $tenant->id);
            }
        }

        // Se tenant está bloqueado e não tem mais faturas atrasadas → desbloquear
        $msg = 'Pagamento registrado.';
        $tenant = $fatura->tenant;
        if ($tenant && $tenant->status === 'bloqueado') {
            $temAtrasadas = FaturaKimobe::where('tenant_id', $tenant->id)
                ->where('status', 'atrasado')
                ->exists();

            if (! $temAtrasadas) {
                $tenant->update(['status' => 'ativo', 'bloqueado_em' => null, 'motivo_bloqueio' => null]);
                $msg .= ' Assinante desbloqueado automaticamente.';
            }
        }

        return redirect()->route('admin.faturamento.index')->with('success', $msg);
    }

    public function cancelar(FaturaKimobe $fatura): RedirectResponse
    {
        if (! in_array($fatura->status, ['pendente', 'atrasado'])) {
            return back()->withErrors(['fatura' => 'Apenas faturas pendentes ou atrasadas podem ser canceladas.']);
        }

        $fatura->update(['status' => 'cancelado']);

        return redirect()->route('admin.faturamento.index')->with('success', 'Fatura cancelada.');
    }
}
