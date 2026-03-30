<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telefone', 20)->nullable()->after('email')->comment('Telefone de contato do usuário');
            $table->string('cpf', 14)->nullable()->after('telefone')->comment('CPF do usuário, formato 000.000.000-00');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telefone', 'cpf']);
        });
    }
};
