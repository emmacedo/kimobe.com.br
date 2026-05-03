<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela única para representar qualquer movimentação financeira no contrato:
 * recorrente (aluguel, condomínio, IPTU mensal), parcelada (IPTU 12x, seguro 4x)
 * ou avulsa (chuveiro, frete, multa). Cada linha é uma ocorrência. Itens
 * recorrentes e parcelados são pré-gerados em N linhas agrupadas por
 * parent_item_id (padrão validado no MoneyMagnet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itens_cobranca', function (Blueprint $table) {
            $table->id()->comment('Identificador único da ocorrência do item');

            $table->foreignId('parent_item_id')
                ->nullable()
                ->constrained('itens_cobranca')
                ->nullOnDelete()
                ->comment('Primeira ocorrência da série; null se é a própria primeira');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono deste item');

            $table->foreignId('contrato_id')
                ->constrained('contratos')
                ->cascadeOnDelete()
                ->comment('Contrato pai do item');

            $table->string('descricao', 255)
                ->comment('Descrição do item (ex: Aluguel, IPTU 2026, Troca de chuveiro)');

            $table->enum('pagante', ['inquilino', 'proprietario', 'administradora'])
                ->comment('Quem é debitado contabilmente — de cujo patrimônio sai o dinheiro');

            $table->enum('recebedor', ['inquilino', 'proprietario', 'administradora'])
                ->comment('Quem é creditado — para cujo patrimônio o dinheiro vai');

            $table->foreignId('entidade_externa_id')
                ->nullable()
                ->constrained('entidades_externas')
                ->nullOnDelete()
                ->comment('Destino externo do dinheiro (intermediação) — síndico, prefeitura, seguradora, prestador');

            $table->enum('tipo', ['recorrente', 'parcelado', 'avulso'])
                ->comment('Categoria do item: recorrente repete por período, parcelado tem N parcelas, avulso é uma única ocorrência');

            $table->enum('periodicidade', ['mensal', 'bimestral', 'trimestral', 'semestral', 'anual'])
                ->nullable()
                ->comment('Frequência apenas para tipo=recorrente; null para parcelado e avulso');

            $table->unsignedSmallInteger('num_parcela')
                ->nullable()
                ->comment('Posição da parcela (ex: 5 de 12). Apenas para tipo=parcelado');

            $table->unsignedSmallInteger('num_parcelas_total')
                ->nullable()
                ->comment('Total de parcelas da série. Apenas para tipo=parcelado');

            $table->decimal('valor_unitario', 10, 2)
                ->comment('Valor desta ocorrência específica. Pode ser negativo para representar abatimentos');

            $table->char('mes_referencia', 7)
                ->comment('Mês de competência no formato MM/YYYY — quando esta ocorrência cai na fatura');

            $table->boolean('visivel_inquilino')
                ->default(true)
                ->comment('Define se o item aparece no extrato visível ao inquilino. Forçado true quando pagante=inquilino');

            $table->enum('status', ['pendente', 'conciliado', 'cancelado'])
                ->default('pendente')
                ->comment('Situação: pendente aguarda conciliação na fatura, conciliado já entrou em fatura fechada, cancelado descartado');

            $table->foreignId('fatura_id')
                ->nullable()
                ->constrained('cobrancas')
                ->nullOnDelete()
                ->comment('Fatura que conciliou este item; null enquanto pendente. Aponta para a tabela `cobrancas` (será renomeada para `faturas`)');

            $table->date('data_pagamento_externo')
                ->nullable()
                ->comment('Data em que a administradora efetivamente pagou a entidade externa (apenas em casos de intermediação)');

            $table->foreignId('pagamento_externo_por_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Operador que registrou o pagamento à entidade externa');

            $table->text('observacoes')
                ->nullable()
                ->comment('Notas livres do operador sobre este item');

            $table->foreignId('criado_por_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Operador que criou o item — preparação multi-usuário');

            $table->foreignId('atualizado_por_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Operador da última atualização');

            $table->timestamps();

            // Índices para consultas frequentes
            $table->index(['tenant_id', 'contrato_id', 'mes_referencia'], 'itens_cobranca_contrato_mes_index');
            $table->index(['tenant_id', 'status', 'mes_referencia'], 'itens_cobranca_status_mes_index');
            $table->index('fatura_id', 'itens_cobranca_fatura_index');
            $table->index('parent_item_id', 'itens_cobranca_parent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itens_cobranca');
    }
};
