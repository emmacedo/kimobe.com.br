<?php

namespace App\Listeners;

use App\Models\FullFlowSubscription;
use Illuminate\Support\Facades\Log;
use Kicol\FullFlow\Events\AbstractWebhookEvent;
use Kicol\FullFlow\Events\SubscriptionActivated;
use Kicol\FullFlow\Events\SubscriptionCancellationScheduled;
use Kicol\FullFlow\Events\SubscriptionEnded;
use Kicol\FullFlow\Events\SubscriptionPastDue;
use Kicol\FullFlow\Events\SubscriptionPaymentReceived;
use Kicol\FullFlow\Events\SubscriptionReactivated;
use Kicol\FullFlow\Events\SubscriptionSuspended;
use Kicol\FullFlow\Events\SubscriptionTrialStarted;

/**
 * Listener wildcard que sincroniza o status da FullFlowSubscription
 * local sempre que o FullFlow dispara um evento via webhook. Mapeia
 * o tipo de evento para o status persistido localmente, garantindo
 * que o middleware EnsureFullFlowSubscriptionActive e a UI sempre
 * reflitam o estado atual.
 */
class SyncFullFlowSubscription
{
    /**
     * Mapa de classe de evento → status local resultante.
     */
    private const STATUS_MAP = [
        SubscriptionTrialStarted::class => 'trial',
        SubscriptionActivated::class => 'ativa',
        SubscriptionPaymentReceived::class => 'ativa',
        SubscriptionReactivated::class => 'ativa',
        SubscriptionPastDue::class => 'past_due',
        SubscriptionSuspended::class => 'suspensa',
        SubscriptionCancellationScheduled::class => 'cancelamento_agendado',
        SubscriptionEnded::class => 'cancelada',
    ];

    public function handle(AbstractWebhookEvent $event): void
    {
        $newStatus = self::STATUS_MAP[$event::class] ?? null;
        if (! $newStatus) {
            return;
        }

        $sub = FullFlowSubscription::where('fullflow_id', $event->subscriptionId())->first();
        if (! $sub) {
            Log::warning('FullFlow webhook recebido para subscription desconhecida', [
                'event' => $event::class,
                'fullflow_id' => $event->subscriptionId(),
                'reference' => $event->externalReference(),
            ]);

            return;
        }

        $data = $event->data();

        $sub->update(array_filter([
            'status' => $newStatus,
            'plan_code' => $data['plan_code'] ?? null,
            'trial_until' => $data['trial_ate'] ?? null,
            'current_period_start' => $data['periodo_inicio'] ?? null,
            'current_period_end' => $data['periodo_fim'] ?? null,
            'amount' => $data['amount'] ?? null,
            'last_synced_at' => now(),
        ], fn ($v) => $v !== null));
    }
}
