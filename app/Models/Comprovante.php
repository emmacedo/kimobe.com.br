<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'owner_type', 'owner_id',
    'tipo', 'arquivo', 'nome_original', 'mime_type', 'tamanho_bytes',
    'valor', 'data_referencia', 'observacoes',
    'enviado_por_user_id', 'enviado_por_papel',
])]
class Comprovante extends Model
{
    protected $table = 'comprovantes';

    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => 'string',
            'tamanho_bytes' => 'integer',
            'valor' => 'decimal:2',
            'data_referencia' => 'date',
            'enviado_por_papel' => 'string',
        ];
    }

    protected $appends = ['url'];

    /**
     * URL pública do arquivo no disco `public`.
     */
    protected function url(): Attribute
    {
        return Attribute::get(
            fn () => $this->arquivo ? Storage::disk('public')->url($this->arquivo) : null
        );
    }

    /**
     * Owner polimórfico — Fatura, Repasse ou ItemCobranca.
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Usuário que fez o upload.
     */
    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_user_id');
    }
}
