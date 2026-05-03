<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renomeia `cobrancas` para `faturas` (modelo unificado — fatura é apenas o
 * agrupador mensal). Remove as 5 colunas fixas (valor_aluguel, valor_condominio,
 * valor_iptu, valor_seguro_incendio, valor_taxa_bombeiros, valor_taxa_extra_condominio)
 * porque agora os valores vêm dos `itens_cobranca`. Renomeia FK em `repasses`
 * (`cobranca_id` → `fatura_id`).
 *
 * Tabelas auxiliares (`cobranca_itens_extras`, `cobranca_comprovantes`) ficam
 * temporariamente com a coluna `cobranca_id` apontando para `faturas` (a FK
 * continua válida após renomeação). Essas tabelas serão dropadas em itens
 * posteriores do plano (4 e 7 respectivamente).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop FK em repasses → cobrancas
        Schema::table('repasses', function (Blueprint $table) {
            $table->dropForeign(['cobranca_id']);
        });

        // 2. Renomear coluna em repasses
        Schema::table('repasses', function (Blueprint $table) {
            $table->renameColumn('cobranca_id', 'fatura_id');
        });

        // 3. Renomear tabela cobrancas → faturas
        Schema::rename('cobrancas', 'faturas');

        // 4. Remover 5 colunas fixas de faturas
        Schema::table('faturas', function (Blueprint $table) {
            $table->dropColumn([
                'valor_aluguel',
                'valor_condominio',
                'valor_iptu',
                'valor_seguro_incendio',
                'valor_taxa_bombeiros',
                'valor_taxa_extra_condominio',
            ]);
        });

        // 5. Recriar FK em repasses apontando para faturas
        Schema::table('repasses', function (Blueprint $table) {
            $table->foreign('fatura_id')
                ->references('id')
                ->on('faturas')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Reverter na ordem inversa
        Schema::table('repasses', function (Blueprint $table) {
            $table->dropForeign(['fatura_id']);
        });

        Schema::table('faturas', function (Blueprint $table) {
            $table->decimal('valor_aluguel', 10, 2)->after('referencia')
                ->comment('Valor do aluguel nesta cobrança');
            $table->decimal('valor_condominio', 10, 2)->nullable()->after('valor_aluguel');
            $table->decimal('valor_iptu', 10, 2)->nullable()->after('valor_condominio');
            $table->decimal('valor_seguro_incendio', 10, 2)->nullable()->after('valor_iptu');
            $table->decimal('valor_taxa_bombeiros', 10, 2)->nullable()->after('valor_seguro_incendio');
            $table->decimal('valor_taxa_extra_condominio', 10, 2)->nullable()->after('valor_taxa_bombeiros');
        });

        Schema::rename('faturas', 'cobrancas');

        Schema::table('repasses', function (Blueprint $table) {
            $table->renameColumn('fatura_id', 'cobranca_id');
        });

        Schema::table('repasses', function (Blueprint $table) {
            $table->foreign('cobranca_id')
                ->references('id')
                ->on('cobrancas')
                ->cascadeOnDelete();
        });
    }
};
