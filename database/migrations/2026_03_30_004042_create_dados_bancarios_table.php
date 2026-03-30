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
        Schema::create('dados_bancarios', function (Blueprint $table) {
            $table->id()->comment('Identificador único do cadastro bancário');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono deste cadastro bancário');

            $table->foreignId('vinculo_id')
                ->constrained('vinculos')
                ->cascadeOnDelete()
                ->comment('Pessoa (via vínculo no tenant) dona desta conta bancária');

            $table->string('apelido', 100)
                ->comment('Nome de identificação rápida da conta (ex: Conta Itaú principal, Nubank PJ)');

            $table->string('banco_codigo', 10)
                ->comment('Código do banco no sistema bancário brasileiro (ex: 341 para Itaú, 001 para BB)');

            $table->string('banco_nome', 100)
                ->comment('Nome do banco para exibição em telas e relatórios');

            $table->string('agencia', 20)
                ->comment('Número da agência bancária com dígito quando aplicável');

            $table->string('conta', 20)
                ->comment('Número da conta corrente ou poupança com dígito');

            $table->enum('tipo_conta', ['corrente', 'poupanca'])
                ->comment('Tipo da conta bancária para geração correta de remessas e transferências');

            $table->enum('pix_tipo', ['cpf', 'cnpj', 'email', 'telefone', 'aleatoria'])
                ->nullable()
                ->comment('Tipo da chave PIX cadastrada, null quando não tem PIX');

            $table->string('pix_chave', 255)
                ->nullable()
                ->comment('Chave PIX para recebimento de repasses via PIX');

            $table->timestamps();

            // Índice para busca de contas por vínculo
            $table->index('vinculo_id', 'dados_bancarios_vinculo_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dados_bancarios');
    }
};
