<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos', function (Blueprint $table) {
            $table->id()->comment('Identificador único do plano de assinatura');
            $table->string('nome', 100)->comment('Nome comercial do plano exibido ao assinante (ex: Starter, Profissional, Business)');
            $table->text('descricao')->nullable()->comment('Descrição do plano com detalhes das funcionalidades incluídas');
            $table->unsignedInteger('limite_imoveis')->comment('Quantidade máxima de imóveis que o assinante pode cadastrar neste plano, 0 significa ilimitado');
            $table->decimal('valor_mensal', 10, 2)->comment('Valor fixo cobrado mensalmente do assinante neste plano em reais');
            $table->enum('status', ['ativo', 'inativo'])->default('ativo')->comment('Planos inativos não podem ser contratados por novos assinantes mas continuam válidos para assinantes existentes');
            $table->unsignedTinyInteger('ordem')->default(0)->comment('Posição de exibição do plano nas listagens e no site público, menor valor aparece primeiro');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planos');
    }
};
