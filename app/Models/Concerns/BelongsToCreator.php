<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait que captura automaticamente quem criou e quem editou pela última vez
 * o registro. Usa `auth()->id()` quando há usuário autenticado; deixa null em
 * cenários sem auth (jobs, seeders, comandos de console).
 *
 * Uso: adicionar `use BelongsToCreator;` nos models cujas tabelas têm
 * `criado_por_user_id` e `atualizado_por_user_id`.
 *
 * Lembre de incluir os dois campos no Fillable para que `update(['atualizado_por_user_id' => ...])`
 * via Mass Assignment funcione (geralmente via observer já está coberto).
 */
trait BelongsToCreator
{
    public static function bootBelongsToCreator(): void
    {
        static::creating(function ($model) {
            if (! $model->criado_por_user_id && auth()->check()) {
                $model->criado_por_user_id = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->atualizado_por_user_id = auth()->id();
            }
        });
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizado_por_user_id');
    }
}
