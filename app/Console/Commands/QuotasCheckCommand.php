<?php

namespace App\Console\Commands;

use App\Mail\Billing\QuotaApproachingLimit;
use App\Models\FullFlowSubscription;
use App\Models\QuotaAlertSent;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Kicol\FullFlow\Models\FullFlowPlan;

/**
 * Varre tenants com FullFlowSubscription ativa e dispara alertas
 * preventivos por e-mail quando o uso de uma quota numérica cruza
 * 80% ou 95%. Reset automático por ciclo (period_marker em
 * quota_alerts_sent).
 *
 * Agendado horário em routes/console.php.
 */
class QuotasCheckCommand extends Command
{
    protected $signature = 'quotas:check {--tenant= : ID específico de Tenant para teste}';

    protected $description = 'Dispara alertas preventivos de quota (80%, 95%) por e-mail';

    private const THRESHOLDS = [95, 80];

    private const NUMERIC_MODULES = ['imoveis'];

    private const ELIGIBLE_STATUSES = ['trial', 'ativa', 'past_due', 'cancelamento_agendado'];

    public function handle(): int
    {
        $tenants = $this->resolveTenants();

        $alertsSent = 0;
        $skipped = 0;

        foreach ($tenants as $tenant) {
            if ($tenant->is_exempt_from_subscription) {
                $skipped++;

                continue;
            }

            $sub = FullFlowSubscription::where('tenant_id', $tenant->id)->first();
            if (! $sub || ! in_array($sub->status, self::ELIGIBLE_STATUSES, true)) {
                $skipped++;

                continue;
            }

            $plan = FullFlowPlan::with('modules')->where('code', $sub->plan_code)->first();
            if (! $plan) {
                $skipped++;

                continue;
            }

            $periodMarker = $this->periodMarker($sub);

            foreach (self::NUMERIC_MODULES as $slug) {
                $module = $plan->modules->firstWhere('slug', $slug);
                if (! $module || $module->type !== 'quantity' || $module->pivot->quota_value === null) {
                    continue;
                }

                $quota = (int) $module->pivot->quota_value;
                if ($quota <= 0) {
                    continue;
                }

                $used = $this->currentUsage($tenant, $slug);
                $percent = ($used / $quota) * 100;

                if ($this->maybeAlert($tenant, $sub, $plan, $slug, $used, $quota, $percent, $periodMarker)) {
                    $alertsSent++;
                }
            }
        }

        $this->info("Alertas enviados: {$alertsSent} | Tenants pulados: {$skipped}");

        return self::SUCCESS;
    }

    private function resolveTenants()
    {
        $query = Tenant::query();

        if ($this->option('tenant')) {
            $query->where('id', (int) $this->option('tenant'));
        }

        return $query->get();
    }

    private function maybeAlert(
        Tenant $tenant,
        FullFlowSubscription $sub,
        FullFlowPlan $plan,
        string $slug,
        int $used,
        int $quota,
        float $percent,
        ?string $periodMarker,
    ): bool {
        foreach (self::THRESHOLDS as $threshold) {
            if ($percent < $threshold) {
                continue;
            }

            $alreadyAlerted = QuotaAlertSent::where('tenant_id', $tenant->id)
                ->where('module_slug', $slug)
                ->where('threshold', '>=', $threshold)
                ->when($periodMarker, fn ($q) => $q->where('period_marker', $periodMarker))
                ->exists();

            if ($alreadyAlerted) {
                return false;
            }

            QuotaAlertSent::create([
                'tenant_id' => $tenant->id,
                'module_slug' => $slug,
                'threshold' => $threshold,
                'period_marker' => $periodMarker,
                'triggered_at' => now(),
            ]);

            $emails = $tenant->getAdminEmails();
            if (empty($emails)) {
                return false;
            }

            try {
                $nextPlan = $this->findNextPlan($plan);
                Mail::to($emails)->send(new QuotaApproachingLimit(
                    tenant: $tenant,
                    moduleSlug: $slug,
                    threshold: $threshold,
                    currentValue: $used,
                    limitValue: $quota,
                    nextPlan: $nextPlan,
                    autoUpgradeEnabled: (bool) $tenant->auto_upgrade_enabled,
                ));
            } catch (\Throwable $e) {
                Log::error('QuotasCheck: falha ao enviar QuotaApproachingLimit', [
                    'tenant_id' => $tenant->id,
                    'slug' => $slug,
                    'threshold' => $threshold,
                    'error' => $e->getMessage(),
                ]);
            }

            return true;
        }

        return false;
    }

    private function currentUsage(Tenant $tenant, string $slug): int
    {
        return match ($slug) {
            'imoveis' => $tenant->totalImoveis(),
            default => 0,
        };
    }

    private function findNextPlan(FullFlowPlan $current): ?FullFlowPlan
    {
        return FullFlowPlan::where('amount', '>', $current->amount)
            ->orderBy('amount', 'asc')
            ->first();
    }

    private function periodMarker(FullFlowSubscription $sub): ?string
    {
        if ($sub->current_period_start) {
            return 'cycle:'.$sub->current_period_start->format('Y-m-d');
        }
        if ($sub->trial_until) {
            return 'trial:'.$sub->trial_until->format('Y-m-d');
        }

        return 'nosub';
    }
}
