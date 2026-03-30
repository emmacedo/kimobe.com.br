<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faturas_kimobe', function (Blueprint $table) {
            $table->id()->comment('Identificador único da fatura do Kimobe ao assinante');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante cobrado nesta fatura');

            $table->foreignId('plano_id')
                ->nullable()
                ->constrained('planos')
                ->nullOnDelete()
                ->comment('Plano vigente no momento da geração desta fatura, null se o plano foi removido');

            $table->string('referencia', 7)->comment('Mês e ano de competência no formato MM/YYYY');
            $table->decimal('valor', 10, 2)->comment('Valor cobrado nesta fatura conforme o plano do assinante');
            $table->date('data_vencimento')->comment('Data de vencimento desta fatura');
            $table->date('data_pagamento')->nullable()->comment('Data em que o pagamento foi registrado, null enquanto não pago');

            $table->enum('metodo_pagamento', ['pix', 'boleto', 'cartao', 'transferencia'])
                ->nullable()
                ->comment('Forma de pagamento utilizada pelo assinante');

            $table->enum('status', ['pendente', 'pago', 'atrasado', 'cancelado'])
                ->default('pendente')
                ->comment('Situação da fatura');

            $table->text('observacoes')->nullable()->comment('Notas internas do super admin sobre esta fatura');
            $table->timestamps();

            $table->unique(['tenant_id', 'referencia'], 'faturas_kimobe_tenant_referencia_unique');
            $table->index(['status', 'data_vencimento'], 'faturas_kimobe_status_vencimento_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faturas_kimobe');
    }
};
