<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCreator;
use App\Models\Concerns\BelongsToTenant;
use App\Observers\ContratoObserver;
use Database\Factories\ContratoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'imovel_id', 'inquilino_vinculo_id', 'data_inicio', 'data_fim', 'valor_aluguel',
    'dia_vencimento', 'modelo_repasse', 'taxa_administracao_pct', 'taxa_seguro_inadimplencia_pct',
    'indice_reajuste', 'mes_reajuste', 'multa_atraso_pct', 'juros_atraso_pct_dia',
    'dias_carencia', 'multa_rescisoria_pct', 'desconto_pontualidade_pct',
    'tipo_garantia', 'status', 'observacoes',
    'criado_por_user_id', 'atualizado_por_user_id',
])]
#[ObservedBy([ContratoObserver::class])]
class Contrato extends Model
{
    /** @use HasFactory<ContratoFactory> */
    use BelongsToCreator, BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    /**
     * Auditoria genérica via spatie/laravel-activitylog (Camada 2).
     * Campos críticos (valor_aluguel, taxa_administracao_pct, modelo_repasse, status,
     * data_fim) são cobertos pela Camada 3 — tabelas dedicadas — e ficam fora daqui.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'multa_atraso_pct', 'juros_atraso_pct_dia', 'dias_carencia',
                'multa_rescisoria_pct', 'desconto_pontualidade_pct',
                'indice_reajuste', 'mes_reajuste', 'dia_vencimento',
                'tipo_garantia', 'observacoes',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

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
     * Inquilino principal (cache de performance — mantido em sincronia com
     * contrato_inquilinos.principal=true). Usado por cobrança e notificações.
     */
    public function inquilino(): BelongsTo
    {
        return $this->belongsTo(Vinculo::class, 'inquilino_vinculo_id');
    }

    /**
     * Todos os inquilinos do contrato (principal + co-inquilinos).
     */
    public function inquilinos(): HasMany
    {
        return $this->hasMany(ContratoInquilino::class);
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
     * Faturas mensais geradas a partir deste contrato.
     */
    public function faturas(): HasMany
    {
        return $this->hasMany(Fatura::class);
    }

    /**
     * Itens de cobrança vinculados a este contrato (modelo unificado: aluguel,
     * responsabilidades, parcelados e avulsos). Substitui gradualmente a relação
     * `responsabilidades`, que será removida quando contrato_responsabilidades for dropada.
     */
    public function itensCobranca(): HasMany
    {
        return $this->hasMany(ItemCobranca::class);
    }

    /**
     * Histórico de reajustes aplicados ao valor_aluguel deste contrato (Camada 3 — auditoria estruturada).
     */
    public function reajustes(): HasMany
    {
        return $this->hasMany(ContratoReajuste::class)->orderBy('data_aplicacao', 'desc');
    }

    /**
     * Histórico de alterações em campos críticos não-financeiros (taxa_administracao_pct,
     * modelo_repasse, status, data_fim) capturadas pelo ContratoObserver.
     */
    public function alteracoes(): HasMany
    {
        return $this->hasMany(ContratoAlteracao::class)->orderBy('alterado_em', 'desc');
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
        $endereco = $imovel->logradouro.', '.$imovel->numero;
        if ($imovel->complemento) {
            $endereco .= ' — '.$imovel->complemento;
        }

        return $endereco;
    }

    /**
     * Endereço curto para listagens — prioriza complemento (ex: "Apto 101")
     * por ser identificador mais útil em tabelas. Cai para "logradouro, numero"
     * quando não há complemento.
     */
    public function getEnderecoCurto(): string
    {
        $imovel = $this->imovel;
        if (! $imovel) {
            return '—';
        }

        return $imovel->complemento
            ?: trim($imovel->logradouro.', '.$imovel->numero, ', ');
    }

    /**
     * Escopo: contratos com status 'ativo'.
     */
    public function scopeAtivo(Builder $query): void
    {
        $query->where('status', 'ativo');
    }

    /**
     * Titularidade responsável do imóvel deste contrato (papel='responsavel').
     * Por convenção há uma única responsável por contrato (decisão MVP).
     */
    public function getTitularResponsavel(): ?Titularidade
    {
        return $this->imovel?->titularidades?->firstWhere('papel', 'responsavel');
    }

    /**
     * Calcula os componentes do repasse para uma titularidade específica:
     * bruto, taxa de administração, seguro de inadimplência e líquido.
     *
     * Centralizado aqui para ser reutilizado tanto na geração persistente
     * (FaturaService::gerarRepasses) quanto no preview on-the-fly
     * (RepasseController::index), eliminando duplicação da fórmula.
     *
     * @return array{bruto: float, taxa_admin: float, seguro: ?float, liquido: float}
     */
    public function calcularRepassePorTitularidade(Titularidade $titularidade): array
    {
        $bruto = round((float) $this->valor_aluguel * (float) $titularidade->percentual / 100, 2);
        $taxaAdmin = round($bruto * (float) $this->taxa_administracao_pct / 100, 2);
        $seguro = $this->taxa_seguro_inadimplencia_pct
            ? round($bruto * (float) $this->taxa_seguro_inadimplencia_pct / 100, 2)
            : null;
        $liquido = $bruto - $taxaAdmin - ($seguro ?? 0);

        return [
            'bruto' => $bruto,
            'taxa_admin' => $taxaAdmin,
            'seguro' => $seguro,
            'liquido' => $liquido,
        ];
    }

    /**
     * Soma o líquido de repasse de todas as titularidades do imóvel.
     * Usado pela visão "1 linha por contrato" no preview de repasses.
     */
    public function calcularRepasseLiquidoTotal(): float
    {
        $titularidades = $this->imovel?->titularidades ?? collect();

        return (float) $titularidades->sum(
            fn (Titularidade $tit) => $this->calcularRepassePorTitularidade($tit)['liquido']
        );
    }
}
