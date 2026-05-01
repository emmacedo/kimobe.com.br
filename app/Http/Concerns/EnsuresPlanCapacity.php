<?php

namespace App\Http\Concerns;

use App\Services\Billing\AutoUpgradeResult;
use App\Services\Billing\AutoUpgradeService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;

/**
 * Trait para Controllers HTTP/Inertia: encapsula a chamada ao
 * AutoUpgradeService e produz `RedirectResponse` ou `null` (se OK).
 *
 * Uso:
 *   if ($redirect = $this->ensureTenantCapacity('imoveis', 1, 'cadastrar mais imóveis')) {
 *       return $redirect;
 *   }
 *   // segue criação
 */
trait EnsuresPlanCapacity
{
    /**
     * Garante que o tenant atual tem capacidade no plano FullFlow
     * para realizar uma operação.
     *
     * Retorna `RedirectResponse` (back com erro) se a operação está bloqueada,
     * ou `null` se pode prosseguir. Em caso de upgrade automático bem-sucedido,
     * dispara session flash informativo.
     */
    protected function ensureTenantCapacity(string $moduleSlug, int $amount, string $action, string $errorField = 'limite'): ?RedirectResponse
    {
        $tenant = app(TenantService::class)->getTenant();
        if (! $tenant) {
            return null; // sem tenant ativo o caller deve ter barrado antes
        }

        $result = app(AutoUpgradeService::class)->ensureCapacityFor($tenant, $moduleSlug, $amount);

        if ($result->resultType === AutoUpgradeResult::UPGRADED) {
            $proration = $result->prorationAmount;
            $msg = $proration !== null && $proration > 0
                ? 'Seu plano foi atualizado automaticamente (cobrança proporcional R$ '.number_format($proration, 2, ',', '.').'). Detalhes enviados por e-mail.'
                : 'Seu plano foi atualizado automaticamente. Detalhes enviados por e-mail.';
            session()->flash('success', $msg);
        }

        if ($result->isAllowed()) {
            return null;
        }

        $errorMsg = match ($result->resultType) {
            AutoUpgradeResult::SKIPPED_DISABLED => 'Upgrade automático está desabilitado em sua conta. Para '.$action.', acesse a página do seu plano e faça upgrade manualmente.',
            AutoUpgradeResult::FAILED => 'Não foi possível verificar a capacidade do seu plano agora. Tente novamente em instantes.',
            default => 'Ação bloqueada.',
        };

        return back()->withErrors([$errorField => $errorMsg]);
    }
}
