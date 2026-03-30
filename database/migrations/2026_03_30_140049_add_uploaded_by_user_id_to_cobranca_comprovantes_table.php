<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobranca_comprovantes', function (Blueprint $table) {
            $table->foreignId('uploaded_by_user_id')
                ->nullable()
                ->after('tamanho_bytes')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Usuário que fez o upload deste comprovante, usado para controlar quem pode remover (inquilino só remove os seus)');
        });
    }

    public function down(): void
    {
        Schema::table('cobranca_comprovantes', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by_user_id']);
            $table->dropColumn('uploaded_by_user_id');
        });
    }
};
