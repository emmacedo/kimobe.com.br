<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id()->comment('Identificador único do administrador da plataforma');
            $table->string('nome', 255)->comment('Nome completo do administrador da plataforma Kimobe');
            $table->string('email', 255)->unique()->comment('Email de acesso ao painel super admin');
            $table->string('senha_hash', 255)->comment('Senha criptografada para autenticação no painel admin');
            $table->rememberToken()->comment('Token para funcionalidade lembrar-me do Laravel');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
