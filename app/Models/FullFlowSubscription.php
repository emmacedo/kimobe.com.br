<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kicol\FullFlow\Models\FullFlowSubscription as BaseFullFlowSubscription;

/**
 * Subscription local do Kimobe, vinculada a Tenant (não User).
 * Substitui o model do package para refletir o modelo multi-tenant
 * do Kimobe (1 Tenant = 1 assinante; um User pode estar em N tenants).
 */
class FullFlowSubscription extends BaseFullFlowSubscription
{
    protected $fillable = [
        'tenant_id',
        'fullflow_id',
        'reference',
        'plan_code',
        'status',
        'trial_until',
        'current_period_start',
        'current_period_end',
        'amount',
        'billing_cycle',
        'last_synced_at',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
