<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'email', 'telefone', 'assunto', 'mensagem', 'ip'])]
class MensagemContato extends Model
{
    protected $table = 'mensagens_contato';

    protected function casts(): array
    {
        return [
            'lida' => 'boolean',
            'respondida' => 'boolean',
            'respondida_em' => 'datetime',
        ];
    }
}
