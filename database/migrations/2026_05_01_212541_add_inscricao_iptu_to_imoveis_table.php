<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('imoveis', function (Blueprint $table) {
            $table->string('inscricao_iptu', 50)
                ->nullable()
                ->after('uf')
                ->comment('Número de inscrição imobiliária municipal usado no IPTU; opcional, varia por município.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imoveis', function (Blueprint $table) {
            $table->dropColumn('inscricao_iptu');
        });
    }
};
