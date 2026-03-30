<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobrancas', function (Blueprint $table) {
            $table->id()->comment('Identificador único da cobrança mensal');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono desta cobrança');

            $table->foreignId('contrato_id')
                ->constrained('contratos')
                ->cascadeOnDelete()
                ->comment('Contrato que originou esta cobrança mensal');

            $table->string('referencia', 7)
                ->comment('Mês e ano de competência no formato MM/YYYY (ex: 03/2026)');

            // Valores fixos da cobrança
            $table->decimal('valor_aluguel', 10, 2)
                ->comment('Valor do aluguel nesta cobrança, pode diferir do contrato em caso de reajuste ou acordo');

            $table->decimal('valor_condominio', 10, 2)
                ->nullable()
                ->comment('Valor do condomínio cobrado do inquilino neste mês, null se a responsabilidade é do proprietário ou não se aplica');

            $table->decimal('valor_iptu', 10, 2)
                ->nullable()
                ->comment('Valor do IPTU cobrado do inquilino neste mês (diluído em 12x ou valor integral), null se responsabilidade do proprietário');

            $table->decimal('valor_seguro_incendio', 10, 2)
                ->nullable()
                ->comment('Valor do seguro incêndio cobrado do inquilino neste mês, null se responsabilidade do proprietário');

            $table->decimal('valor_taxa_bombeiros', 10, 2)
                ->nullable()
                ->comment('Valor da taxa de bombeiros cobrada do inquilino neste mês, null se responsabilidade do proprietário');

            $table->decimal('valor_taxa_extra_condominio', 10, 2)
                ->nullable()
                ->comment('Valor de taxa extra de condomínio (obra, fundo de reserva, etc.) cobrada do inquilino, null quando não há taxa extra');

            $table->decimal('valor_total', 10, 2)
                ->comment('Soma de todos os valores fixos + itens extras, é o valor final da cobrança antes de desconto/juros/multa');

            // Acréscimos e descontos
            $table->decimal('valor_desconto', 10, 2)
                ->nullable()
                ->comment('Desconto concedido por pontualidade, aplicado quando o inquilino paga até a data de vencimento');

            $table->decimal('valor_juros', 10, 2)
                ->nullable()
                ->comment('Juros calculados sobre dias de atraso, preenchido no momento da baixa do pagamento');

            $table->decimal('valor_multa', 10, 2)
                ->nullable()
                ->comment('Multa por atraso aplicada conforme percentual definido no contrato');

            $table->decimal('valor_pago', 10, 2)
                ->nullable()
                ->comment('Valor efetivamente recebido do inquilino, pode diferir do total por desconto, juros, multa ou pagamento parcial');

            // Datas e pagamento
            $table->date('data_vencimento')
                ->comment('Data de vencimento desta cobrança, definida pelo dia_vencimento do contrato');

            $table->date('data_pagamento')
                ->nullable()
                ->comment('Data em que o pagamento foi registrado, null enquanto não pago');

            $table->enum('metodo_pagamento', ['boleto', 'pix', 'transferencia', 'dinheiro'])
                ->nullable()
                ->comment('Forma de pagamento utilizada pelo inquilino, preenchido no momento da baixa');

            $table->enum('tipo_geracao', ['automatica', 'manual'])
                ->default('automatica')
                ->comment('Como esta cobrança foi criada: automática pelo sistema no ciclo mensal ou manual pelo operador');

            $table->enum('status', ['pendente', 'pago', 'atrasado', 'cancelado'])
                ->default('pendente')
                ->comment('Situação da cobrança: pendente aguardando pagamento, pago com baixa confirmada, atrasado após vencimento, cancelado por estorno ou erro');

            $table->string('url_boleto', 500)
                ->nullable()
                ->comment('URL do boleto bancário gerado para esta cobrança, integração futura com gateway de pagamento');

            $table->text('observacoes')
                ->nullable()
                ->comment('Notas do operador sobre esta cobrança, como motivo de cancelamento ou detalhes do pagamento');

            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'cobrancas_tenant_status_index');
            $table->unique(['contrato_id', 'referencia'], 'cobrancas_contrato_referencia_unique');
            $table->index(['tenant_id', 'data_vencimento'], 'cobrancas_tenant_vencimento_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas');
    }
};
