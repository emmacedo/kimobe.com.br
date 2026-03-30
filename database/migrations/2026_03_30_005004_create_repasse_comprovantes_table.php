<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repasse_comprovantes', function (Blueprint $table) {
            $table->id()->comment('Identificador único do comprovante de repasse');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono deste comprovante');

            $table->foreignId('repasse_id')
                ->constrained('repasses')
                ->cascadeOnDelete()
                ->comment('Repasse ao qual este comprovante de transferência se refere');

            $table->string('caminho', 500)
                ->comment('Path no storage do Laravel (ex: comprovantes/repasses/789/transferencia.pdf)');

            $table->string('nome_arquivo', 255)
                ->comment('Nome original do arquivo no momento do upload');

            $table->string('mime_type', 50)
                ->comment('Tipo do arquivo para validação e exibição');

            $table->unsignedInteger('tamanho_bytes')
                ->comment('Tamanho do arquivo em bytes para controle de armazenamento');

            $table->text('observacoes')
                ->nullable()
                ->comment('Notas sobre o comprovante de transferência');

            $table->timestamps();

            $table->index('repasse_id', 'repasse_comprovantes_repasse_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repasse_comprovantes');
    }
};
