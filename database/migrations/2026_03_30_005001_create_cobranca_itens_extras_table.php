<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranca_itens_extras', function (Blueprint $table) {
            $table->id()->comment('Identificador único do item extra');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono deste item');

            $table->foreignId('cobranca_id')
                ->constrained('cobrancas')
                ->cascadeOnDelete()
                ->comment('Cobrança à qual este item extra pertence');

            $table->string('descricao', 255)
                ->comment('Descrição do item extra (ex: Pintura do hall - rateio, Taxa de mudança, Reparo emergencial)');

            $table->decimal('valor', 10, 2)
                ->comment('Valor deste item extra a ser somado no total da cobrança');

            $table->text('observacoes')
                ->nullable()
                ->comment('Detalhes adicionais sobre este item, como justificativa ou referência de aprovação');

            $table->timestamps();

            $table->index('cobranca_id', 'cobranca_itens_extras_cobranca_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_itens_extras');
    }
};
