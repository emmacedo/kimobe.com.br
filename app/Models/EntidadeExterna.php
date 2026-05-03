<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCreator;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\EntidadeExternaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'nome', 'tipo', 'cpf_cnpj', 'telefone', 'email', 'site',
    'contato_interno_nome',
    'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
    'observacoes',
    'criado_por_user_id', 'atualizado_por_user_id',
])]
class EntidadeExterna extends Model
{
    /** @use HasFactory<EntidadeExternaFactory> */
    use BelongsToCreator, BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'nome', 'tipo', 'cpf_cnpj', 'telefone', 'email', 'site',
                'contato_interno_nome', 'cep', 'logradouro', 'numero', 'complemento',
                'bairro', 'cidade', 'uf', 'observacoes',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected $table = 'entidades_externas';

    /**
     * Condomínios administrados por esta entidade.
     */
    public function condominios(): HasMany
    {
        return $this->hasMany(Condominio::class);
    }
}
