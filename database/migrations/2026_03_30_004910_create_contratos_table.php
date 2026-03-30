<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id()->comment('Identificador único do contrato de locação');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono deste contrato');

            $table->foreignId('imovel_id')
                ->constrained('imoveis')
                ->cascadeOnDelete()
                ->comment('Imóvel objeto da locação');

            $table->foreignId('inquilino_vinculo_id')
                ->constrained('vinculos')
                ->cascadeOnDelete()
                ->comment('Inquilino locatário vinculado a este contrato');

            // Vigência
            $table->date('data_inicio')
                ->comment('Data de início da vigência do contrato de locação');

            $table->date('data_fim')
                ->comment('Data de término previsto do contrato, após essa data pode ser renovado ou encerrado');

            // Valores e cobrança
            $table->decimal('valor_aluguel', 10, 2)
                ->comment('Valor mensal do aluguel definido no contrato, base para geração das cobranças');

            $table->unsignedTinyInteger('dia_vencimento')
                ->comment('Dia do mês em que o aluguel vence (1 a 28), usado para gerar cobranças mensais');

            // Modelo de repasse ao proprietário
            $table->enum('modelo_repasse', ['por_recebimento', 'garantido'])
                ->comment('Define quando o proprietário recebe: por_recebimento só após o inquilino pagar, garantido na data fixa independente do pagamento');

            $table->decimal('taxa_administracao_pct', 5, 2)
                ->comment('Percentual de taxa de administração cobrado pela imobiliária sobre o aluguel (ex: 10.00 = 10%)');

            $table->decimal('taxa_seguro_inadimplencia_pct', 5, 2)
                ->nullable()
                ->comment('Percentual adicional do seguro inadimplência, só aplicável quando modelo_repasse é garantido (ex: 4.00 = 4%)');

            // Reajuste
            $table->enum('indice_reajuste', ['igpm', 'ipca', 'fixo'])
                ->comment('Índice econômico usado para reajuste anual do aluguel');

            $table->unsignedTinyInteger('mes_reajuste')
                ->comment('Mês do ano em que o reajuste é aplicado (1 a 12), geralmente o mês de aniversário do contrato');

            // Multas e juros
            $table->decimal('multa_atraso_pct', 5, 2)
                ->default(2.00)
                ->comment('Percentual de multa aplicado sobre o valor da cobrança quando o inquilino atrasa o pagamento');

            $table->decimal('juros_atraso_pct_dia', 5, 4)
                ->default(0.0333)
                ->comment('Percentual de juros por dia de atraso (ex: 0.0333 = 1% ao mês / 30 dias)');

            $table->unsignedTinyInteger('dias_carencia')
                ->default(0)
                ->comment('Dias após o vencimento antes de aplicar multa e juros, 0 significa cobrança imediata');

            $table->decimal('multa_rescisoria_pct', 5, 2)
                ->nullable()
                ->comment('Percentual de multa sobre o saldo restante do contrato em caso de rescisão antecipada pelo inquilino');

            $table->decimal('desconto_pontualidade_pct', 5, 2)
                ->nullable()
                ->comment('Percentual de desconto concedido ao inquilino quando paga até a data de vencimento');

            // Garantia e status
            $table->enum('tipo_garantia', ['caucao', 'fiador', 'seguro_fianca', 'titulo_capitalizacao', 'sem_garantia'])
                ->comment('Tipo de garantia locatícia escolhida para este contrato');

            $table->enum('status', ['ativo', 'encerrado', 'renovacao', 'cancelado'])
                ->default('ativo')
                ->comment('Situação do contrato: ativo em vigor, encerrado ao término, em renovação sendo renegociado, cancelado por rescisão');

            $table->text('observacoes')
                ->nullable()
                ->comment('Notas internas do administrador sobre o contrato, cláusulas especiais ou condições particulares');

            $table->timestamps();
            $table->softDeletes()->comment('Data de exclusão lógica — contrato removido mas preservado para histórico financeiro');

            // Índices para consultas frequentes
            $table->index(['tenant_id', 'status'], 'contratos_tenant_status_index');
            $table->index(['tenant_id', 'imovel_id'], 'contratos_tenant_imovel_index');
            $table->index('inquilino_vinculo_id', 'contratos_inquilino_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
