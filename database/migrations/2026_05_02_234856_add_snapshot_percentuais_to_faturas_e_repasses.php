<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frente 2 — Snapshot de percentuais aplicados.
 *
 * Faturas e repasses passam a guardar não só os valores monetários calculados
 * mas também os percentuais e parâmetros que foram efetivamente aplicados
 * no momento da geração/baixa. Isso protege a auditoria contra mudanças
 * posteriores no contrato.
 *
 * Adiciona também colunas de autoria (gerada_por / baixada_por / realizada_por)
 * preparando para multi-usuário (Princípio 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->decimal('multa_atraso_pct_aplicada', 5, 2)->nullable()->after('valor_multa')
                ->comment('Percentual de multa de atraso vigente no momento da baixa');

            $table->decimal('juros_atraso_pct_dia_aplicada', 5, 4)->nullable()->after('multa_atraso_pct_aplicada')
                ->comment('Percentual de juros diário vigente no momento da baixa');

            $table->decimal('desconto_pontualidade_pct_aplicada', 5, 2)->nullable()->after('juros_atraso_pct_dia_aplicada')
                ->comment('Percentual de desconto por pontualidade vigente quando aplicável');

            $table->unsignedTinyInteger('dias_carencia_aplicada')->nullable()->after('desconto_pontualidade_pct_aplicada')
                ->comment('Dias de carência vigentes na geração da fatura');

            $table->foreignId('gerada_por_user_id')->nullable()->after('dias_carencia_aplicada')
                ->constrained('users')->nullOnDelete()
                ->comment('Operador que gerou a fatura; null se geração via job automático');

            $table->foreignId('baixada_por_user_id')->nullable()->after('gerada_por_user_id')
                ->constrained('users')->nullOnDelete()
                ->comment('Operador que registrou o pagamento (baixa)');
        });

        Schema::table('repasses', function (Blueprint $table) {
            $table->decimal('taxa_administracao_pct_aplicada', 5, 2)->nullable()->after('taxa_administracao_valor')
                ->comment('Percentual de taxa de administração vigente na geração do repasse');

            $table->decimal('taxa_seguro_inadimplencia_pct_aplicada', 5, 2)->nullable()->after('taxa_seguro_inadimplencia_valor')
                ->comment('Percentual de seguro inadimplência aplicado (apenas modelo garantido)');

            $table->decimal('percentual_titularidade_aplicado', 5, 2)->nullable()->after('taxa_seguro_inadimplencia_pct_aplicada')
                ->comment('Percentual da titularidade no momento da geração');

            $table->foreignId('gerado_por_user_id')->nullable()->after('percentual_titularidade_aplicado')
                ->constrained('users')->nullOnDelete()
                ->comment('Operador que gerou o repasse; null se via job automático');

            $table->foreignId('realizado_por_user_id')->nullable()->after('gerado_por_user_id')
                ->constrained('users')->nullOnDelete()
                ->comment('Operador que confirmou o repasse (transferência)');
        });
    }

    public function down(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->dropForeign(['gerada_por_user_id']);
            $table->dropForeign(['baixada_por_user_id']);
            $table->dropColumn([
                'multa_atraso_pct_aplicada',
                'juros_atraso_pct_dia_aplicada',
                'desconto_pontualidade_pct_aplicada',
                'dias_carencia_aplicada',
                'gerada_por_user_id',
                'baixada_por_user_id',
            ]);
        });

        Schema::table('repasses', function (Blueprint $table) {
            $table->dropForeign(['gerado_por_user_id']);
            $table->dropForeign(['realizado_por_user_id']);
            $table->dropColumn([
                'taxa_administracao_pct_aplicada',
                'taxa_seguro_inadimplencia_pct_aplicada',
                'percentual_titularidade_aplicado',
                'gerado_por_user_id',
                'realizado_por_user_id',
            ]);
        });
    }
};
