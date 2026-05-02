<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ContratoInquilinoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['contrato_id', 'vinculo_id', 'principal'])]
class ContratoInquilino extends Model
{
    /** @use HasFactory<ContratoInquilinoFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'contrato_inquilinos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'principal' => 'boolean',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function vinculo(): BelongsTo
    {
        return $this->belongsTo(Vinculo::class);
    }
}
