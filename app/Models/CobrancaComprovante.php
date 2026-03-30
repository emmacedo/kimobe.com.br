<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CobrancaComprovanteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['cobranca_id', 'caminho', 'nome_arquivo', 'mime_type', 'tamanho_bytes', 'uploaded_by_user_id', 'observacoes'])]
class CobrancaComprovante extends Model
{
    /** @use HasFactory<CobrancaComprovanteFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'cobranca_comprovantes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tamanho_bytes' => 'integer',
        ];
    }

    protected $appends = ['url'];

    protected function url(): Attribute
    {
        return Attribute::get(
            fn () => $this->caminho ? Storage::disk('public')->url($this->caminho) : null
        );
    }

    /**
     * Cobrança à qual este comprovante se refere.
     */
    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }

    /**
     * Usuário que fez o upload deste comprovante.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
