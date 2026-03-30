<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garantias', function (Blueprint $table) {
            $table->id()->comment('Identificador único da garantia locatícia');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono desta garantia');

            $table->foreignId('contrato_id')
                ->constrained('contratos')
                ->cascadeOnDelete()
                ->comment('Contrato ao qual esta garantia está vinculada');

            $table->enum('tipo', ['caucao', 'fiador', 'seguro_fianca', 'titulo_capitalizacao'])
                ->comment('Tipo de garantia, deve corresponder ao tipo_garantia definido no contrato');

            $table->decimal('valor', 10, 2)
                ->nullable()
                ->comment('Valor monetário da garantia: valor depositado (caução), valor do prêmio (seguro fiança), ou valor do título (capitalização)');

            $table->string('seguradora', 255)
                ->nullable()
                ->comment('Nome da seguradora, preenchido apenas quando tipo é seguro_fianca');

            $table->string('numero_apolice', 100)
                ->nullable()
                ->comment('Número da apólice do seguro fiança para referência e acionamento');

            $table->string('numero_titulo', 100)
                ->nullable()
                ->comment('Número do título de capitalização para referência e resgate');

            $table->date('data_inicio')
                ->nullable()
                ->comment('Data de início da vigência da garantia');

            $table->date('data_fim')
                ->nullable()
                ->comment('Data de vencimento da garantia, importante para renovação de seguros e títulos');

            $table->enum('status', ['ativo', 'vencido', 'cancelado', 'resgatado'])
                ->default('ativo')
                ->comment('Situação da garantia: ativo em vigor, vencido sem renovação, cancelado, ou resgatado (caução devolvida / título resgatado)');

            $table->text('observacoes')
                ->nullable()
                ->comment('Detalhes adicionais como condições de acionamento ou dados complementares da garantia');

            $table->timestamps();

            $table->index('contrato_id', 'garantias_contrato_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garantias');
    }
};
