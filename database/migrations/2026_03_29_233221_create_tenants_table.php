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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id()->comment('Identificador único do tenant (assinante do sistema)');

            $table->string('nome', 255)
                ->comment('Nome fantasia ou razão social do assinante');

            $table->enum('tipo', ['imobiliaria', 'proprietario_direto'])
                ->comment('Modelo de uso: imobiliária administrando terceiros ou proprietário gerindo seus próprios imóveis');

            $table->string('documento', 20)
                ->unique()
                ->comment('CPF (proprietário direto) ou CNPJ (imobiliária) do assinante — usado como identificador fiscal único');

            $table->enum('plano', ['basico', 'profissional', 'enterprise'])
                ->default('basico')
                ->comment('Plano de assinatura contratado, define limites de imóveis e funcionalidades disponíveis');

            $table->enum('status', ['ativo', 'suspenso', 'cancelado'])
                ->default('ativo')
                ->comment('Situação da assinatura: ativo, suspenso por inadimplência, ou cancelado definitivamente');

            $table->timestamps();
            $table->softDeletes()->comment('Data de exclusão lógica — tenant removido mas preservado para histórico e auditoria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
