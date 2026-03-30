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
        Schema::create('vinculos', function (Blueprint $table) {
            $table->id()->comment('Identificador único do vínculo entre usuário e tenant');

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('Usuário vinculado a este tenant — referência à tabela users');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Tenant (assinante) ao qual o usuário está vinculado');

            $table->enum('papel', ['admin', 'proprietario', 'inquilino'])
                ->comment('Papel do usuário neste tenant: admin gerencia tudo, proprietário acompanha seus imóveis, inquilino acessa contratos e boletos');

            $table->enum('status', ['ativo', 'inativo', 'pendente'])
                ->default('pendente')
                ->comment('Situação do vínculo: pendente aguarda aceite do convite, ativo com acesso liberado, inativo com acesso revogado');

            $table->timestamps();

            // Um usuário não pode ter o mesmo papel duplicado no mesmo tenant,
            // mas PODE ter papéis diferentes (ex: admin + proprietario)
            $table->unique(['user_id', 'tenant_id', 'papel'], 'vinculos_user_tenant_papel_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vinculos');
    }
};
