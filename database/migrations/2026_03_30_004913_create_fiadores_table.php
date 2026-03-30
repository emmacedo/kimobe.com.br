<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiadores', function (Blueprint $table) {
            $table->id()->comment('Identificador único do fiador');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono deste cadastro de fiador');

            $table->foreignId('contrato_id')
                ->constrained('contratos')
                ->cascadeOnDelete()
                ->comment('Contrato no qual esta pessoa é fiadora');

            $table->string('nome', 255)
                ->comment('Nome completo do fiador conforme documento de identidade');

            $table->string('cpf', 14)
                ->comment('CPF do fiador, formato 000.000.000-00, obrigatório para contratos com fiador');

            $table->string('rg', 20)
                ->nullable()
                ->comment('Número do RG do fiador para identificação em contrato');

            $table->string('telefone', 20)
                ->comment('Telefone de contato principal do fiador');

            $table->string('email', 255)
                ->nullable()
                ->comment('Email do fiador para comunicações e notificações');

            $table->string('profissao', 255)
                ->nullable()
                ->comment('Profissão do fiador, comumente exigida em contratos de locação');

            $table->string('estado_civil', 50)
                ->nullable()
                ->comment('Estado civil do fiador, relevante para inclusão de cônjuge como fiador solidário');

            // Endereço residencial
            $table->string('cep', 9)
                ->comment('CEP do endereço residencial do fiador');

            $table->string('logradouro', 255)
                ->comment('Rua, avenida ou via do endereço residencial do fiador');

            $table->string('numero', 20)
                ->comment('Número do endereço residencial do fiador');

            $table->string('complemento', 255)
                ->nullable()
                ->comment('Complemento do endereço residencial do fiador');

            $table->string('bairro', 255)
                ->comment('Bairro do endereço residencial do fiador');

            $table->string('cidade', 255)
                ->comment('Cidade do endereço residencial do fiador');

            $table->char('uf', 2)
                ->comment('Estado do endereço residencial do fiador');

            $table->timestamps();

            $table->index('contrato_id', 'fiadores_contrato_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiadores');
    }
};
