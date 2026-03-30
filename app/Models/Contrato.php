<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ContratoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'imovel_id', 'inquilino_vinculo_id', 'data_inicio', 'data_fim', 'valor_aluguel',
    'dia_vencimento', 'modelo_repasse', 'taxa_administracao_pct', 'taxa_seguro_inadimplencia_pct',
    'indice_reajuste', 'mes_reajuste', 'multa_atraso_pct', 'juros_atraso_pct_dia',
    'dias_carencia', 'multa_rescisoria_pct', 'desconto_pontualidade_pct',
    'tipo_garantia', 'status', 'observacoes',
])]
class Contrato extends Model
{
    /** @use HasFactory<ContratoFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'contratos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'valor_aluguel' => 'decimal:2',
            'dia_vencimento' => 'integer',
            'modelo_repasse' => 'string',
            'taxa_administracao_pct' => 'decimal:2',
            'taxa_seguro_inadimplencia_pct' => 'decimal:2',
            'indice_reajuste' => 'string',
            'mes_reajuste' => 'integer',
            'multa_atraso_pct' => 'decimal:2',
            'juros_atraso_pct_dia' => 'decimal:4',
            'dias_carencia' => 'integer',
            'multa_rescisoria_pct' => 'decimal:2',
            'desconto_pontualidade_pct' => 'decimal:2',
            'tipo_garantia' => 'string',
            'status' => 'string',
        ];
    }

    /**
     * Imóvel objeto da locação.
     */
    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class);
    }

    /**
     * Inquilino locatário (via vínculo no tenant).
     */
    public function inquilino(): BelongsTo
    {
        return $this->belongsTo(Vinculo::class, 'inquilino_vinculo_id');
    }

    /**
     * Responsabilidades financeiras do contrato (IPTU, condomínio, etc.).
     */
    public function responsabilidades(): HasMany
    {
        return $this->hasMany(ContratoResponsabilidade::class);
    }

    /**
     * Garantia locatícia do contrato.
     */
    public function garantia(): HasOne
    {
        return $this->hasOne(Garantia::class);
    }

    /**
     * Fiadores do contrato.
     */
    public function fiadores(): HasMany
    {
        return $this->hasMany(Fiador::class);
    }

    /**
     * Cobranças mensais geradas a partir deste contrato.
     */
    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }

    public function getEmailInquilino(): ?string
    {
        return $this->inquilino?->user?->email;
    }

    public function getNomeInquilino(): ?string
    {
        return $this->inquilino?->user?->name;
    }

    public function getEnderecoResumido(): string
    {
        $imovel = $this->imovel;
        $endereco = $imovel->logradouro . ', ' . $imovel->numero;
        if ($imovel->complemento) {
            $endereco .= ' — ' . $imovel->complemento;
        }
        return $endereco;
    }
}
