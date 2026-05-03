<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['modulo', 'chave', 'nome', 'descricao', 'assunto', 'corpo_html', 'corpo_texto', 'variaveis_disponiveis', 'ativo'])]
class EmailTemplate extends Model
{
    use LogsActivity;

    protected $table = 'email_templates';

    /**
     * Auditoria de mudanças em templates do super admin — afetam comunicação.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['assunto', 'corpo_html', 'corpo_texto', 'variaveis_disponiveis', 'ativo'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'variaveis_disponiveis' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function scopeModuloKimobe(Builder $query): Builder
    {
        return $query->where('modulo', 'kimobe');
    }

    public function scopeModuloAdmin(Builder $query): Builder
    {
        return $query->where('modulo', 'admin');
    }

    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /**
     * Substitui variáveis no formato {{variavel}} por valores.
     */
    private function substituirVariaveis(string $texto, array $variaveis): string
    {
        foreach ($variaveis as $chave => $valor) {
            $texto = str_replace("{{{$chave}}}", (string) $valor, $texto);
        }

        return $texto;
    }

    public function renderAssunto(array $variaveis): string
    {
        return $this->substituirVariaveis($this->assunto, $variaveis);
    }

    public function renderCorpoHtml(array $variaveis): string
    {
        return $this->substituirVariaveis($this->corpo_html, $variaveis);
    }

    public function renderCorpoTexto(array $variaveis): ?string
    {
        return $this->corpo_texto ? $this->substituirVariaveis($this->corpo_texto, $variaveis) : null;
    }
}
