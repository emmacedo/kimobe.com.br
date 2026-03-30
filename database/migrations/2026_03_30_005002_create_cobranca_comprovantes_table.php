<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranca_comprovantes', function (Blueprint $table) {
            $table->id()->comment('Identificador único do comprovante de pagamento');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono deste comprovante');

            $table->foreignId('cobranca_id')
                ->constrained('cobrancas')
                ->cascadeOnDelete()
                ->comment('Cobrança à qual este comprovante se refere');

            $table->string('caminho', 500)
                ->comment('Path no storage do Laravel (ex: comprovantes/cobrancas/456/recibo.pdf)');

            $table->string('nome_arquivo', 255)
                ->comment('Nome original do arquivo no momento do upload');

            $table->string('mime_type', 50)
                ->comment('Tipo do arquivo (ex: application/pdf, image/jpeg) para validação e exibição');

            $table->unsignedInteger('tamanho_bytes')
                ->comment('Tamanho do arquivo em bytes para controle de armazenamento');

            $table->text('observacoes')
                ->nullable()
                ->comment('Notas sobre o comprovante (ex: pagamento parcial, comprovante corrigido)');

            $table->timestamps();

            $table->index('cobranca_id', 'cobranca_comprovantes_cobranca_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_comprovantes');
    }
};
