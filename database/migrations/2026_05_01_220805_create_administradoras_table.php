<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administradoras', function (Blueprint $table) {
            $table->id()->comment('Identificador único da administradora');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono deste cadastro de administradora');

            $table->string('nome', 255)
                ->comment('Razão social ou nome do síndico/profissional que administra o condomínio');

            $table->string('cpf_cnpj', 14)
                ->nullable()
                ->comment('CPF (11 dígitos) ou CNPJ (14 dígitos), armazenado apenas com dígitos');

            $table->string('telefone', 11)
                ->nullable()
                ->comment('Telefone de contato, armazenado apenas com dígitos');

            $table->string('email', 255)
                ->nullable()
                ->comment('Email de contato da administradora');

            $table->string('site', 255)
                ->nullable()
                ->comment('URL do site institucional ou portal da administradora');

            $table->string('contato_interno_nome', 255)
                ->nullable()
                ->comment('Nome da pessoa de contato dentro da administradora (atendimento direto)');

            // Endereço (todos opcionais)
            $table->string('cep', 8)
                ->nullable()
                ->comment('CEP da administradora, armazenado apenas com dígitos');

            $table->string('logradouro', 255)
                ->nullable()
                ->comment('Rua, avenida ou via da administradora');

            $table->string('numero', 20)
                ->nullable()
                ->comment('Número no logradouro');

            $table->string('complemento', 255)
                ->nullable()
                ->comment('Sala, andar, bloco ou outra identificação complementar');

            $table->string('bairro', 255)
                ->nullable()
                ->comment('Bairro da administradora');

            $table->string('cidade', 255)
                ->nullable()
                ->comment('Cidade da administradora');

            $table->char('uf', 2)
                ->nullable()
                ->comment('Sigla do estado da administradora');

            $table->text('observacoes')
                ->nullable()
                ->comment('Notas internas sobre a administradora — horário de atendimento, particularidades etc.');

            $table->timestamps();
            $table->softDeletes()->comment('Data de exclusão lógica — administradora removida mas preservada para histórico de condomínios');

            $table->index(['tenant_id', 'nome'], 'administradoras_tenant_nome_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administradoras');
    }
};
