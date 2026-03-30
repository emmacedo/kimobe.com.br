<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'dias_aviso_antes_vencimento', 'aviso_no_dia_vencimento',
    'dias_graca_apos_vencimento', 'dias_aviso_bloqueio',
    'aviso_ao_bloquear', 'dia_vencimento_fatura',
])]
class ConfiguracaoCobrancaKimobe extends Model
{
    protected $table = 'configuracoes_cobranca_kimobe';

    protected function casts(): array
    {
        return [
            'aviso_no_dia_vencimento' => 'boolean',
            'aviso_ao_bloquear' => 'boolean',
        ];
    }

    /**
     * Retorna o registro de configuração, criando com defaults se não existir.
     */
    public static function getConfig(): self
    {
        return static::firstOrCreate([], [
            'dias_aviso_antes_vencimento' => 5,
            'aviso_no_dia_vencimento' => true,
            'dias_graca_apos_vencimento' => 7,
            'dias_aviso_bloqueio' => 3,
            'aviso_ao_bloquear' => true,
            'dia_vencimento_fatura' => 10,
        ]);
    }
}
