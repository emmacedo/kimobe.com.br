<?php

namespace App\Models;

use Database\Factories\AdminUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['nome', 'email', 'senha_hash'])]
#[Hidden(['senha_hash', 'remember_token'])]
class AdminUser extends Authenticatable
{
    /** @use HasFactory<AdminUserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'admin_users';

    /**
     * Override para usar a coluna 'senha_hash' como senha de autenticação.
     */
    public function getAuthPassword(): string
    {
        return $this->senha_hash;
    }
}
