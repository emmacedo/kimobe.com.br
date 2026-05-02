<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela pivot que permite múltiplos inquilinos por contrato. O campo 'principal'
     * marca o inquilino que é o cache em contratos.inquilino_vinculo_id (responsável
     * pelo pagamento, destinatário das notificações). Apenas 1 'principal' por contrato.
     */
    public function up(): void
    {
        Schema::create('contrato_inquilinos', function (Blueprint $table) {
            $table->id()->comment('Identificador único do vínculo inquilino-contrato');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono deste vínculo');

            $table->foreignId('contrato_id')
                ->constrained('contratos')
                ->cascadeOnDelete()
                ->comment('Contrato ao qual este inquilino está vinculado');

            $table->foreignId('vinculo_id')
                ->constrained('vinculos')
                ->cascadeOnDelete()
                ->comment('Vínculo (User × Tenant com papel=inquilino) deste inquilino');

            $table->boolean('principal')
                ->default(false)
                ->comment('Inquilino principal do contrato — responsável pelo pagamento e destinatário das notificações');

            $table->timestamps();
            $table->softDeletes()->comment('Data de exclusão lógica — preserva histórico para auditoria de cobranças');

            // Unicidade: um mesmo vínculo não pode aparecer 2x no mesmo contrato (incluindo trashed,
            // tratado via restore no controller).
            $table->unique(['contrato_id', 'vinculo_id'], 'contrato_inquilinos_contrato_vinculo_unique');

            // Índices para queries comuns (scope de inquilino, busca de "meus contratos").
            $table->index(['vinculo_id'], 'contrato_inquilinos_vinculo_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_inquilinos');
    }
};
