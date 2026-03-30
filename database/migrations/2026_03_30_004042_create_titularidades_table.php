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
        Schema::create('titularidades', function (Blueprint $table) {
            $table->id()->comment('Identificador único da titularidade');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono deste vínculo de titularidade');

            $table->foreignId('imovel_id')
                ->constrained('imoveis')
                ->cascadeOnDelete()
                ->comment('Imóvel ao qual esta titularidade se refere');

            $table->foreignId('vinculo_id')
                ->constrained('vinculos')
                ->cascadeOnDelete()
                ->comment('Pessoa titular (proprietário) deste imóvel via seu vínculo no tenant');

            $table->foreignId('dados_bancarios_id')
                ->nullable()
                ->constrained('dados_bancarios')
                ->nullOnDelete()
                ->comment('Conta bancária onde este titular recebe os repasses deste imóvel específico');

            $table->enum('tipo_titular', ['pessoa_fisica', 'empresa', 'inventario'])
                ->comment('Natureza jurídica do titular: pessoa física individual, empresa/PJ, ou espólio em processo de inventário');

            $table->enum('papel', ['responsavel', 'observador'])
                ->comment('Responsável toma decisões e assina documentos, observador acompanha rendimentos e recebe sua parte do repasse');

            $table->decimal('percentual', 5, 2)
                ->comment('Fração de propriedade do imóvel em percentual (ex: 50.00), a soma de todos os titulares de um imóvel deve ser 100.00');

            $table->timestamps();

            // Um titular não pode estar duplicado no mesmo imóvel
            $table->unique(['imovel_id', 'vinculo_id'], 'titularidades_imovel_vinculo_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('titularidades');
    }
};
