<?php

namespace App\Http\Middleware;

use App\Models\FullFlowSubscription;
use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia rotas do painel quando o tenant não tem assinatura FullFlow
 * ativa. Tenants `is_exempt_from_subscription = true` passam livremente.
 *
 * Status que liberam acesso (vem de config/fullflow.php):
 *   trial, ativa, past_due, cancelamento_agendado.
 *
 * Em caso de bloqueio:
 *   - request Inertia → redirect para /settings/plano com flash de erro
 *   - request normal → mesmo comportamento (Inertia trata redirect)
 */
class EnsureFullFlowSubscriptionActive
{
    public function __construct(
        protected TenantService $tenantService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantService->getTenant();
        if (! $tenant) {
            return $next($request);
        }

        if ($tenant->is_exempt_from_subscription) {
            return $next($request);
        }

        $sub = FullFlowSubscription::where('tenant_id', $tenant->id)->first();
        $allowed = config('fullflow.access_allowed_statuses', ['trial', 'ativa', 'past_due', 'cancelamento_agendado']);

        if ($sub && in_array($sub->status, $allowed, true)) {
            return $next($request);
        }

        $msg = $sub
            ? "Sua assinatura está {$sub->status}. Acesse seu plano para regularizar."
            : 'Você ainda não tem uma assinatura ativa. Escolha um plano para continuar.';

        return redirect()->route('settings.plano')->with('error', $msg);
    }
}
