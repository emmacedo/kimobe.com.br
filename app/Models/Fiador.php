<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCreator;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FiadorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'contrato_id', 'nome', 'cpf', 'rg', 'telefone', 'email', 'profissao', 'estado_civil',
    'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
    'criado_por_user_id', 'atualizado_por_user_id',
])]
class Fiador extends Model
{
    /** @use HasFactory<FiadorFactory> */
    use BelongsToCreator, BelongsToTenant, HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'nome', 'cpf', 'rg', 'telefone', 'email', 'profissao', 'estado_civil',
                'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected $table = 'fiadores';

    /**
     * Contrato no qual esta pessoa é fiadora.
     */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
