<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_responsabilidades', function (Blueprint $table) {
            $table->id()->comment('Identificador único da responsabilidade financeira');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono desta responsabilidade');

            $table->foreignId('contrato_id')
                ->constrained('contratos')
                ->cascadeOnDelete()
                ->comment('Contrato ao qual esta responsabilidade está vinculada');

            $table->string('descricao', 255)
                ->comment('Nome da responsabilidade financeira (ex: IPTU, Condomínio, Seguro incêndio)');

            $table->enum('responsavel', ['proprietario', 'inquilino'])
                ->comment('Quem arca com este custo: proprietário desconta do repasse, inquilino soma na cobrança mensal');

            $table->decimal('valor', 10, 2)
                ->nullable()
                ->comment('Valor estimado desta responsabilidade, null quando o valor é variável mês a mês');

            $table->enum('periodicidade', ['mensal', 'anual', 'avulso'])
                ->default('mensal')
                ->comment('Frequência da cobrança: mensal inclui todo mês, anual é diluída em 12x ou cobrada de uma vez, avulso é cobrança pontual');

            $table->boolean('predefinido')
                ->default(false)
                ->comment('Indica se este item veio da lista pré-definida do sistema (IPTU, Condomínio, etc.) ou foi adicionado manualmente pelo operador');

            $table->text('observacoes')
                ->nullable()
                ->comment('Detalhes adicionais como forma de rateio, datas de vigência ou condições especiais');

            $table->timestamps();

            $table->index('contrato_id', 'contrato_responsabilidades_contrato_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_responsabilidades');
    }
};
