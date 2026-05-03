<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_alteracoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->foreignId('alterado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('alterado_em')->useCurrent();
            $table->string('campo', 50);
            $table->json('valor_anterior')->nullable();
            $table->json('valor_novo')->nullable();
            $table->date('data_efetiva');
            $table->text('motivo')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'contrato_id', 'campo']);
            $table->index(['tenant_id', 'contrato_id', 'data_efetiva']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_alteracoes');
    }
};
