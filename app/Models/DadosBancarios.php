<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DadosBancariosFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'vinculo_id', 'apelido', 'banco_codigo', 'banco_nome', 'agencia', 'conta',
    'tipo_conta', 'pix_tipo', 'pix_chave',
])]
class DadosBancarios extends Model
{
    /** @use HasFactory<DadosBancariosFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'dados_bancarios';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_conta' => 'string',
            'pix_tipo' => 'string',
        ];
    }

    /**
     * Vínculo (pessoa no tenant) dona desta conta bancária.
     */
    public function vinculo(): BelongsTo
    {
        return $this->belongsTo(Vinculo::class);
    }
}
