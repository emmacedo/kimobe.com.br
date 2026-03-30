<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'template_id', 'tenant_id', 'destinatario_email', 'destinatario_nome',
    'assunto', 'chave_template', 'variaveis_usadas', 'status', 'erro',
    'enviado_em', 'aberto', 'aberto_em', 'aberturas_count',
    'ip_abertura', 'user_agent_abertura', 'token_rastreamento',
])]
class EmailLog extends Model
{
    protected $table = 'email_logs';

    protected function casts(): array
    {
        return [
            'variaveis_usadas' => 'array',
            'aberto' => 'boolean',
            'enviado_em' => 'datetime',
            'aberto_em' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function gerarToken(): string
    {
        do {
            $token = Str::random(64);
        } while (static::where('token_rastreamento', $token)->exists());

        return $token;
    }

    public function marcarAberto(string $ip, string $userAgent): void
    {
        $this->increment('aberturas_count');

        if (! $this->aberto) {
            $this->update([
                'aberto' => true,
                'aberto_em' => now(),
                'ip_abertura' => $ip,
                'user_agent_abertura' => Str::limit($userAgent, 500),
            ]);
        }
    }
}
