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
        Schema::create('imovel_fotos', function (Blueprint $table) {
            $table->id()->comment('Identificador único da foto');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono desta foto');

            $table->foreignId('imovel_id')
                ->constrained('imoveis')
                ->cascadeOnDelete()
                ->comment('Imóvel ao qual esta foto pertence');

            $table->string('caminho', 500)
                ->comment('Path no storage do Laravel (ex: imoveis/123/sala.jpg)');

            $table->string('nome_arquivo', 255)
                ->comment('Nome original do arquivo no momento do upload');

            $table->string('legenda', 255)
                ->nullable()
                ->comment('Descrição da foto para identificação (ex: Sala de estar, Cozinha)');

            $table->unsignedTinyInteger('ordem')
                ->default(1)
                ->comment('Posição de exibição da foto, ordem 1 é automaticamente a foto principal/destaque do imóvel');

            $table->string('mime_type', 50)
                ->comment('Tipo do arquivo (ex: image/jpeg, image/png) para validação e exibição');

            $table->unsignedInteger('tamanho_bytes')
                ->comment('Tamanho do arquivo em bytes para controle de armazenamento');

            $table->timestamps();

            // Índice para ordenação de fotos por imóvel
            $table->index(['imovel_id', 'ordem'], 'imovel_fotos_imovel_ordem_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imovel_fotos');
    }
};
