<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Remove `contrato_responsabilidades` (legado). As responsabilidades financeiras
 * agora são representadas como `itens_cobranca` (tipo=recorrente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('contrato_responsabilidades');
    }

    public function down(): void
    {
        // Sem rollback automático — para recriar a estrutura original consultar
        // `2026_03_30_004911_create_contrato_responsabilidades_table.php`.
    }
};
