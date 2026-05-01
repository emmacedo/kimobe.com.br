<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoUpgradeLog extends Model
{
    protected $table = 'auto_upgrade_log';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'fullflow_subscription_id',
        'trigger_module',
        'required_amount',
        'from_plan_code',
        'to_plan_code',
        'result',
        'proration_amount',
        'error_message',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'required_amount' => 'integer',
            'proration_amount' => 'decimal:2',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
