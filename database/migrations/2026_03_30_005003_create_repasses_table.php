<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repasses', function (Blueprint $table) {
            $table->id()->comment('Identificador único do repasse ao proprietário');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono deste repasse');

            $table->foreignId('cobranca_id')
                ->constrained('cobrancas')
                ->cascadeOnDelete()
                ->comment('Cobrança que originou este repasse');

            $table->foreignId('titularidade_id')
                ->constrained('titularidades')
                ->cascadeOnDelete()
                ->comment('Titular que receberá este repasse, carrega o percentual de propriedade e dados bancários');

            // Valores do repasse
            $table->decimal('valor_aluguel_bruto', 10, 2)
                ->comment('Parte do aluguel que cabe a este titular com base no seu percentual de propriedade (ex: 50% de R$ 2.400 = R$ 1.200)');

            $table->decimal('taxa_administracao_valor', 10, 2)
                ->comment('Valor da taxa de administração descontada deste repasse (percentual do contrato sobre o valor bruto do titular)');

            $table->decimal('taxa_seguro_inadimplencia_valor', 10, 2)
                ->nullable()
                ->comment('Valor do seguro inadimplência descontado, só quando o contrato tem modelo garantido');

            $table->decimal('valor_liquido', 10, 2)
                ->comment('Valor final que o titular recebe: bruto - taxa administração - seguro inadimplência');

            // Datas e status
            $table->date('data_prevista')
                ->comment('Data prevista para realização do repasse ao titular');

            $table->date('data_realizada')
                ->nullable()
                ->comment('Data em que o repasse foi efetivamente transferido ao titular, null enquanto pendente');

            $table->enum('status', ['pendente', 'realizado', 'cancelado'])
                ->default('pendente')
                ->comment('Situação do repasse: pendente aguardando transferência, realizado com confirmação, cancelado por estorno ou erro');

            $table->text('observacoes')
                ->nullable()
                ->comment('Notas do operador sobre este repasse, como justificativa de atraso ou detalhes da transferência');

            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'repasses_tenant_status_index');
            $table->index('cobranca_id', 'repasses_cobranca_index');
            $table->index('titularidade_id', 'repasses_titularidade_index');
            $table->index(['tenant_id', 'data_prevista'], 'repasses_tenant_data_prevista_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repasses');
    }
};
