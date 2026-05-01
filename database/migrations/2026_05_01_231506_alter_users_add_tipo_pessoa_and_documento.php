<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generaliza o cadastro de pessoa: PF ou PJ. Renomeia 'cpf' para 'documento'
     * e adiciona 'tipo_pessoa' para distinção. Preserva os dados existentes.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('tipo_pessoa', ['pf', 'pj'])
                ->default('pf')
                ->after('telefone')
                ->comment('Natureza jurídica do cadastro: pf=pessoa física, pj=pessoa jurídica');

            $table->string('documento', 14)
                ->nullable()
                ->after('tipo_pessoa')
                ->comment('CPF (11 dígitos) ou CNPJ (14 dígitos), armazenado apenas com dígitos');
        });

        // Migra dados existentes de cpf → documento
        DB::statement('UPDATE users SET documento = cpf WHERE cpf IS NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('cpf');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cpf', 14)->nullable()->after('telefone')->comment('CPF do usuário, formato 000.000.000-00');
        });

        // Restaura cpf a partir de documento APENAS quando for de PF
        DB::statement("UPDATE users SET cpf = documento WHERE tipo_pessoa = 'pf' AND documento IS NOT NULL");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tipo_pessoa', 'documento']);
        });
    }
};
