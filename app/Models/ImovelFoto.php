<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ImovelFotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'imovel_id', 'caminho', 'nome_arquivo', 'legenda', 'ordem', 'mime_type', 'tamanho_bytes',
])]
class ImovelFoto extends Model
{
    /** @use HasFactory<ImovelFotoFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'imovel_fotos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'tamanho_bytes' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected $appends = ['url'];

    /**
     * URL pública da foto no storage.
     */
    protected function url(): Attribute
    {
        return Attribute::get(
            fn () => $this->caminho ? Storage::disk('public')->url($this->caminho) : null
        );
    }

    /**
     * Imóvel ao qual esta foto pertence.
     */
    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class);
    }
}
