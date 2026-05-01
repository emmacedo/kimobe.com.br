<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotaAlertSent extends Model
{
    protected $table = 'quota_alerts_sent';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'module_slug',
        'threshold',
        'period_marker',
        'triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'integer',
            'triggered_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
