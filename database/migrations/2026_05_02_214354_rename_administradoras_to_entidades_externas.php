<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renomeia `administradoras` para `entidades_externas` e generaliza o cadastro
 * para qualquer entidade externa ao tenant (síndico, prefeitura, seguradora,
 * prestadores de serviço etc.). Adiciona a coluna `tipo` para discriminar
 * a categoria. Renomeia também a FK em `condominios`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Remover FK existente em condominios → administradoras
        Schema::table('condominios', function (Blueprint $table) {
            $table->dropForeign(['administradora_id']);
        });

        // 2. Renomear tabela
        Schema::rename('administradoras', 'entidades_externas');

        // 3. Adicionar coluna `tipo` com default para registros existentes
        Schema::table('entidades_externas', function (Blueprint $table) {
            $table->enum('tipo', [
                'administradora_condominio',
                'sindico',
                'prefeitura',
                'seguradora',
                'prestador_servico',
                'empresa',
                'pessoa_fisica',
                'outro',
            ])->default('administradora_condominio')->after('nome')
                ->comment('Categoria da entidade externa: administradora de condomínio, síndico, prefeitura, seguradora, prestador de serviço, empresa, pessoa física ou outro');
        });

        // 4. Renomear coluna FK em condominios
        Schema::table('condominios', function (Blueprint $table) {
            $table->renameColumn('administradora_id', 'entidade_externa_id');
        });

        // 5. Recriar FK apontando para a nova tabela
        Schema::table('condominios', function (Blueprint $table) {
            $table->foreign('entidade_externa_id')
                ->references('id')
                ->on('entidades_externas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Inverter na ordem oposta
        Schema::table('condominios', function (Blueprint $table) {
            $table->dropForeign(['entidade_externa_id']);
        });

        Schema::table('condominios', function (Blueprint $table) {
            $table->renameColumn('entidade_externa_id', 'administradora_id');
        });

        Schema::table('entidades_externas', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });

        Schema::rename('entidades_externas', 'administradoras');

        Schema::table('condominios', function (Blueprint $table) {
            $table->foreign('administradora_id')
                ->references('id')
                ->on('administradoras')
                ->nullOnDelete();
        });
    }
};
