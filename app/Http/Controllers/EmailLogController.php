<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = app(TenantService::class)->getTenantId();

        $query = EmailLog::where('tenant_id', $tenantId)
            ->where('chave_template', 'like', 'admin.%')
            ->with('template')
            ->orderBy('created_at', 'desc');

        if ($busca = $request->input('busca')) {
            $query->where(fn ($q) => $q->where('destinatario_email', 'like', "%{$busca}%")->orWhere('assunto', 'like', "%{$busca}%"));
        }
        if ($status = $request->input('status')) $query->where('status', $status);

        $logs = $query->paginate(20)->withQueryString();

        $resumo = [
            'enviados_mes' => EmailLog::where('tenant_id', $tenantId)->where('chave_template', 'like', 'admin.%')->where('status', 'enviado')->whereMonth('created_at', now()->month)->count(),
            'taxa_abertura' => $this->calcularTaxa($tenantId),
            'falhas_mes' => EmailLog::where('tenant_id', $tenantId)->where('chave_template', 'like', 'admin.%')->where('status', 'falha')->whereMonth('created_at', now()->month)->count(),
        ];

        return Inertia::render('emails/index', [
            'logs' => $logs, 'resumo' => $resumo,
            'filtros' => ['busca' => $request->input('busca', ''), 'status' => $request->input('status', '')],
        ]);
    }

    public function show(EmailLog $emailLog): JsonResponse
    {
        $tenantId = app(TenantService::class)->getTenantId();
        abort_unless($emailLog->tenant_id === $tenantId, 403);

        $emailLog->load('template');
        return response()->json($emailLog);
    }

    private function calcularTaxa(int $tenantId): float
    {
        $enviados = EmailLog::where('tenant_id', $tenantId)->where('status', 'enviado')->where('enviado_em', '>=', now()->subDays(30))->count();
        if ($enviados === 0) return 0;
        $abertos = EmailLog::where('tenant_id', $tenantId)->where('status', 'enviado')->where('aberto', true)->where('enviado_em', '>=', now()->subDays(30))->count();
        return round(($abertos / $enviados) * 100, 1);
    }
}
