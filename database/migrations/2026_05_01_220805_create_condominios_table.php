<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condominios', function (Blueprint $table) {
            $table->id()->comment('Identificador único do registro de condomínio');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono deste cadastro de condomínio');

            $table->foreignId('imovel_id')
                ->unique()
                ->constrained('imoveis')
                ->cascadeOnDelete()
                ->comment('Imóvel ao qual estes dados de condomínio pertencem (1:1)');

            $table->foreignId('administradora_id')
                ->nullable()
                ->constrained('administradoras')
                ->nullOnDelete()
                ->comment('Administradora opcionalmente associada a este condomínio');

            $table->unsignedTinyInteger('dia_vencimento')
                ->nullable()
                ->comment('Dia do mês em que a taxa do condomínio vence (1-31)');

            $table->decimal('valor', 10, 2)
                ->nullable()
                ->comment('Valor mensal da taxa de condomínio em reais');

            $table->string('acesso_login', 255)
                ->nullable()
                ->comment('Login de acesso ao sistema/portal da administradora referente a este imóvel');

            $table->string('acesso_senha', 255)
                ->nullable()
                ->comment('Senha de acesso ao sistema/portal da administradora — armazenada em texto plano por opção do operador');

            $table->text('acesso_descricao')
                ->nullable()
                ->comment('Descrição livre sobre o acesso — URL do portal, observações sobre login, contas vinculadas etc.');

            $table->timestamps();
            $table->softDeletes()->comment('Data de exclusão lógica do registro de condomínio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condominios');
    }
};
