<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_reajustes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->foreignId('alterado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('alterado_em')->useCurrent();
            $table->date('data_aplicacao');
            $table->decimal('valor_anterior', 10, 2);
            $table->decimal('valor_novo', 10, 2);
            $table->decimal('percentual', 7, 4);
            $table->enum('indice_usado', ['igpm', 'ipca', 'fixo', 'manual']);
            $table->enum('origem', ['reajuste_anual', 'aditivo', 'renegociacao', 'correcao']);
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'contrato_id', 'data_aplicacao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_reajustes');
    }
};
