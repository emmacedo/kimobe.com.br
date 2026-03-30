<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RepasseComprovanteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['repasse_id', 'caminho', 'nome_arquivo', 'mime_type', 'tamanho_bytes', 'observacoes'])]
class RepasseComprovante extends Model
{
    /** @use HasFactory<RepasseComprovanteFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'repasse_comprovantes';

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
     * Repasse ao qual este comprovante se refere.
     */
    public function repasse(): BelongsTo
    {
        return $this->belongsTo(Repasse::class);
    }
}
