<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ImovelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
    'tipo', 'status', 'quartos', 'suites', 'banheiros', 'vagas_garagem',
    'andar', 'area_m2', 'valor_aluguel_sugerido', 'observacoes',
])]
class Imovel extends Model
{
    /** @use HasFactory<ImovelFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'imoveis';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => 'string',
            'status' => 'string',
            'quartos' => 'integer',
            'suites' => 'integer',
            'banheiros' => 'integer',
            'vagas_garagem' => 'integer',
            'andar' => 'integer',
            'area_m2' => 'decimal:2',
            'valor_aluguel_sugerido' => 'decimal:2',
        ];
    }

    /**
     * Titularidades (proprietários) deste imóvel.
     */
    public function titularidades(): HasMany
    {
        return $this->hasMany(Titularidade::class);
    }

    /**
     * Fotos do imóvel ordenadas pela posição de exibição.
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(ImovelFoto::class)->orderBy('ordem');
    }

    /**
     * Foto principal (menor ordem) do imóvel.
     */
    public function fotoPrincipal(): HasOne
    {
        return $this->hasOne(ImovelFoto::class)->oldestOfMany('ordem');
    }

    /**
     * Contratos de locação deste imóvel.
     */
    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    /**
     * Retorna titulares com email/nome para envio de notificações.
     */
    public function getTitularesParaNotificacao(): \Illuminate\Support\Collection
    {
        return $this->titularidades()
            ->with('vinculo.user')
            ->get()
            ->map(fn ($t) => [
                'email' => $t->vinculo->user->email,
                'nome' => $t->vinculo->user->name,
            ]);
    }
}
