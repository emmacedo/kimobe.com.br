<?php

namespace App\Services\Billing;

use App\Mail\Billing\AutoUpgradePerformed;
use App\Mail\Billing\KicolBillingAlert;
use App\Mail\Billing\TopPlanOverageNotice;
use App\Models\AutoUpgradeLog;
use App\Models\FullFlowSubscription;
use App\Models\QuotaAlertSent;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Kicol\FullFlow\Exceptions\FullFlowException;
use Kicol\FullFlow\Facades\FullFlow;
use Kicol\FullFlow\Models\FullFlowModule;
use Kicol\FullFlow\Models\FullFlowPlan;

/**
 * Garante que o tenant tem capacidade no plano FullFlow para realizar uma
 * operação (cadastrar imóvel, ativar feature, etc.). Se o plano atual não
 * cobre, faz upgrade automático para o próximo plano da escada que cubra
 * TODAS as quotas/features atuais + a nova operação.
 *
 * Comportamento quando estourou e não há plano superior (decisão δ):
 *   - LIBERA a operação
 *   - registra overage_top_plan no log
 *   - dispara e-mail ao tenant (TopPlanOverageNotice) e à Kicol (KicolBillingAlert)
 *     com debouncing por ciclo
 */
class AutoUpgradeService
{
    public function ensureCapacityFor(Tenant $tenant, string $moduleSlug, int $requiredAmount = 1): AutoUpgradeResult
    {
        if ($tenant->is_exempt_from_subscription) {
            return AutoUpgradeResult::ok();
        }

        $context = DB::transaction(function () use ($tenant, $moduleSlug, $requiredAmount) {
            $sub = FullFlowSubscription::where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();

            if (! $sub) {
                $this->log($tenant, null, $moduleSlug, $requiredAmount, null, null, AutoUpgradeResult::FAILED, null, 'no_subscription');

                return ['result' => AutoUpgradeResult::failed('Sem assinatura FullFlow ativa.')];
            }

            if ($this->planCovers($sub->plan_code, $moduleSlug, $requiredAmount, $tenant)) {
                return ['result' => AutoUpgradeResult::ok()];
            }

            if (! $tenant->auto_upgrade_enabled) {
                $this->log($tenant, $sub->id, $moduleSlug, $requiredAmount, $sub->plan_code, null, AutoUpgradeResult::SKIPPED_DISABLED);

                return ['result' => AutoUpgradeResult::skippedDisabled()];
            }

            $newPlan = $this->findCheapestPlanCovering($tenant, $sub, $moduleSlug, $requiredAmount);

            if (! $newPlan) {
                $this->log($tenant, $sub->id, $moduleSlug, $requiredAmount, $sub->plan_code, null, AutoUpgradeResult::OVERAGE_TOP_PLAN);

                return [
                    'result' => AutoUpgradeResult::overageTopPlan(),
                    'sub' => $sub,
                ];
            }

            try {
                $result = FullFlow::upgradeSubscription(
                    $sub->fullflow_id,
                    $newPlan->code,
                    "Auto-upgrade: limite de {$moduleSlug} excedido"
                );
            } catch (FullFlowException $e) {
                Log::error('AutoUpgrade: falha no FullFlow', [
                    'tenant_id' => $tenant->id,
                    'from' => $sub->plan_code,
                    'to' => $newPlan->code,
                    'error' => $e->getMessage(),
                ]);
                $this->log($tenant, $sub->id, $moduleSlug, $requiredAmount, $sub->plan_code, $newPlan->code, AutoUpgradeResult::FAILED, null, $e->getMessage());

                return ['result' => AutoUpgradeResult::failed('Falha ao executar upgrade no FullFlow: '.$e->getMessage())];
            }

            $proration = isset($result['proration_amount']) ? (float) $result['proration_amount'] : null;
            $fromPlanCode = $sub->plan_code;

            $sub->update([
                'plan_code' => $result['plan_code'] ?? $newPlan->code,
                'amount' => $result['amount'] ?? $newPlan->amount,
                'billing_cycle' => $newPlan->billing_cycle,
                'last_synced_at' => now(),
            ]);

            $this->log($tenant, $sub->id, $moduleSlug, $requiredAmount, $fromPlanCode, $newPlan->code, AutoUpgradeResult::UPGRADED, $proration);

            return [
                'result' => AutoUpgradeResult::upgraded($newPlan->code, $proration),
                'fromPlanCode' => $fromPlanCode,
                'newPlan' => $newPlan,
                'proration' => $proration,
            ];
        });

        $this->dispatchSideEffectEmails($tenant, $moduleSlug, $context);

        return $context['result'];
    }

    private function planCovers(?string $planCode, string $moduleSlug, int $required, Tenant $tenant): bool
    {
        if (! $planCode) {
            return false;
        }

        $plan = FullFlowPlan::with('modules')->where('code', $planCode)->first();
        if (! $plan) {
            return false;
        }

        $module = $plan->modules->firstWhere('slug', $moduleSlug);
        if (! $module) {
            return false;
        }

        if ($module->type === 'boolean') {
            return true;
        }

        $usedNow = $this->currentUsage($tenant, $moduleSlug);
        $quota = (int) $module->pivot->quota_value;

        return ($usedNow + $required) <= $quota;
    }

    /**
     * Uso real corrente do tenant para um módulo de quantidade.
     * No Kimobe, hoje só `imoveis` é numérico.
     */
    private function currentUsage(Tenant $tenant, string $moduleSlug): int
    {
        return match ($moduleSlug) {
            'imoveis' => $tenant->totalImoveis(),
            default => 0,
        };
    }

    private function findCheapestPlanCovering(Tenant $tenant, FullFlowSubscription $sub, string $newSlug, int $newAmount): ?FullFlowPlan
    {
        $currentPlan = FullFlowPlan::with('modules')->where('code', $sub->plan_code)->first();

        $requiredQuotas = [];
        $requiredFeatures = [];

        if ($currentPlan) {
            foreach ($currentPlan->modules as $m) {
                if ($m->type === 'boolean') {
                    $requiredFeatures[] = $m->slug;
                } elseif ($m->type === 'quantity') {
                    $requiredQuotas[$m->slug] = $this->currentUsage($tenant, $m->slug);
                }
            }
        }

        $newType = FullFlowModule::where('slug', $newSlug)->value('type');

        if ($newType === 'boolean') {
            $requiredFeatures[] = $newSlug;
        } elseif ($newType === 'quantity') {
            $current = $requiredQuotas[$newSlug] ?? $this->currentUsage($tenant, $newSlug);
            $requiredQuotas[$newSlug] = $current + $newAmount;
        }

        $requiredFeatures = array_values(array_unique($requiredFeatures));

        $candidates = FullFlowPlan::with('modules')
            ->where('amount', '>', $sub->amount)
            ->orderBy('amount', 'asc')
            ->get();

        foreach ($candidates as $candidate) {
            if ($this->planSatisfies($candidate, $requiredQuotas, $requiredFeatures)) {
                return $candidate;
            }
        }

        return null;
    }

    private function planSatisfies(FullFlowPlan $plan, array $requiredQuotas, array $requiredFeatures): bool
    {
        $modulesBySlug = $plan->modules->keyBy('slug');

        foreach ($requiredFeatures as $slug) {
            if (! $modulesBySlug->has($slug)) {
                return false;
            }
        }

        foreach ($requiredQuotas as $slug => $needed) {
            $module = $modulesBySlug->get($slug);
            if (! $module) {
                return false;
            }
            if ((int) $module->pivot->quota_value < $needed) {
                return false;
            }
        }

        return true;
    }

    private function dispatchSideEffectEmails(Tenant $tenant, string $moduleSlug, array $context): void
    {
        $result = $context['result'];

        if ($result->resultType === AutoUpgradeResult::UPGRADED) {
            $fromPlan = isset($context['fromPlanCode'])
                ? FullFlowPlan::where('code', $context['fromPlanCode'])->first()
                : null;

            $newPlan = $context['newPlan'];

            $emails = $tenant->getAdminEmails();
            if (empty($emails)) {
                return;
            }

            try {
                Mail::to($emails)->send(new AutoUpgradePerformed(
                    tenant: $tenant,
                    fromPlan: $fromPlan,
                    toPlan: $newPlan,
                    triggerModule: $moduleSlug,
                    prorationAmount: $context['proration'] ?? null,
                ));
            } catch (\Throwable $e) {
                Log::error('AutoUpgrade: falha ao enviar AutoUpgradePerformed', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return;
        }

        if ($result->resultType === AutoUpgradeResult::OVERAGE_TOP_PLAN) {
            $sub = $context['sub'] ?? null;
            $periodMarker = $this->periodMarker($sub);

            $alreadyAlerted = QuotaAlertSent::where('tenant_id', $tenant->id)
                ->where('module_slug', $moduleSlug)
                ->where('threshold', 999)
                ->when($periodMarker, fn ($q) => $q->where('period_marker', $periodMarker))
                ->exists();

            if ($alreadyAlerted) {
                return;
            }

            QuotaAlertSent::create([
                'tenant_id' => $tenant->id,
                'module_slug' => $moduleSlug,
                'threshold' => 999,
                'period_marker' => $periodMarker,
                'triggered_at' => now(),
            ]);

            $tenantEmails = $tenant->getAdminEmails();
            if (! empty($tenantEmails)) {
                try {
                    Mail::to($tenantEmails)->send(new TopPlanOverageNotice(
                        tenant: $tenant,
                        triggerModule: $moduleSlug,
                    ));
                } catch (\Throwable $e) {
                    Log::error('AutoUpgrade: falha ao enviar TopPlanOverageNotice', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $kicolEmails = $this->kicolNotificationEmails();
            if (! empty($kicolEmails)) {
                try {
                    Mail::to($kicolEmails)->send(new KicolBillingAlert(
                        tenant: $tenant,
                        triggerModule: $moduleSlug,
                        currentPlanCode: $sub?->plan_code,
                    ));
                } catch (\Throwable $e) {
                    Log::error('AutoUpgrade: falha ao enviar KicolBillingAlert', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function periodMarker(?FullFlowSubscription $sub): ?string
    {
        if (! $sub) {
            return null;
        }
        if ($sub->current_period_start) {
            return 'cycle:'.$sub->current_period_start->format('Y-m-d');
        }
        if ($sub->trial_until) {
            return 'trial:'.$sub->trial_until->format('Y-m-d');
        }

        return 'nosub';
    }

    /**
     * @return array<int, string>
     */
    private function kicolNotificationEmails(): array
    {
        $raw = (string) config('billing.kicol_notifications_email', '');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function log(
        Tenant $tenant,
        ?int $subscriptionId,
        string $moduleSlug,
        ?int $requiredAmount,
        ?string $fromPlan,
        ?string $toPlan,
        string $result,
        ?float $proration = null,
        ?string $error = null,
    ): AutoUpgradeLog {
        return AutoUpgradeLog::create([
            'tenant_id' => $tenant->id,
            'fullflow_subscription_id' => $subscriptionId,
            'trigger_module' => $moduleSlug,
            'required_amount' => $requiredAmount,
            'from_plan_code' => $fromPlan,
            'to_plan_code' => $toPlan,
            'result' => $result,
            'proration_amount' => $proration,
            'error_message' => $error,
            'created_at' => now(),
        ]);
    }
}
