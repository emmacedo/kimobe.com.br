<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Services\EmailNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminEmailLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = EmailLog::with('template')->orderBy('created_at', 'desc');

        if ($busca = $request->input('busca')) {
            $query->where(fn ($q) => $q->where('destinatario_email', 'like', "%{$busca}%")->orWhere('assunto', 'like', "%{$busca}%"));
        }
        if ($status = $request->input('status')) $query->where('status', $status);
        if ($chave = $request->input('chave_template')) $query->where('chave_template', $chave);
        if ($aberto = $request->input('aberto')) {
            $query->where('aberto', $aberto === 'sim');
        }

        $logs = $query->paginate(30)->withQueryString();

        $resumo = [
            'enviados_hoje' => EmailLog::where('status', 'enviado')->whereDate('enviado_em', today())->count(),
            'taxa_abertura' => $this->calcularTaxaAbertura(),
            'falhas_hoje' => EmailLog::where('status', 'falha')->whereDate('created_at', today())->count(),
            'na_fila' => EmailLog::where('status', 'pendente')->count(),
        ];

        return Inertia::render('admin/emails/index', [
            'logs' => $logs, 'resumo' => $resumo,
            'filtros' => ['busca' => $request->input('busca', ''), 'status' => $request->input('status', ''), 'chave_template' => $request->input('chave_template', ''), 'aberto' => $request->input('aberto', '')],
        ]);
    }

    public function show(EmailLog $emailLog): JsonResponse
    {
        $emailLog->load('template');
        return response()->json($emailLog);
    }

    public function reenviar(EmailLog $emailLog): JsonResponse
    {
        $log = app(EmailNotificationService::class)->enviar(
            $emailLog->chave_template,
            $emailLog->destinatario_email,
            $emailLog->destinatario_nome ?? '',
            $emailLog->variaveis_usadas ?? [],
            $emailLog->tenant_id,
        );

        return response()->json(['message' => 'Email reenviado.', 'novo_log_id' => $log->id]);
    }

    private function calcularTaxaAbertura(): float
    {
        $enviados = EmailLog::where('status', 'enviado')->where('enviado_em', '>=', now()->subDays(30))->count();
        if ($enviados === 0) return 0;
        $abertos = EmailLog::where('status', 'enviado')->where('aberto', true)->where('enviado_em', '>=', now()->subDays(30))->count();
        return round(($abertos / $enviados) * 100, 1);
    }
}
