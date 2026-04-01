<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Páginas institucionais editáveis pelo super admin (termos de uso, privacidade, etc.).
 */
class PaginaInstitucional extends Model
{
    protected $table = 'paginas_institucionais';

    protected $fillable = [
        'slug',
        'titulo',
        'conteudo',
        'meta_description',
        'atualizado_por',
        'publicado',
    ];

    protected function casts(): array
    {
        return [
            'publicado' => 'boolean',
        ];
    }

    /**
     * Último admin que editou esta página.
     */
    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'atualizado_por');
    }

    /**
     * Scope: apenas páginas publicadas.
     */
    public function scopePublicado($query)
    {
        return $query->where('publicado', true);
    }

    /**
     * Busca uma página publicada pelo slug.
     */
    public static function getBySlug(string $slug): ?self
    {
        return static::publicado()->where('slug', $slug)->first();
    }
}
