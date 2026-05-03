<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Remove `cobranca_itens_extras` (legado). Os itens extras agora são parte da
 * tabela unificada `itens_cobranca` com `tipo=avulso`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cobranca_itens_extras');
    }

    public function down(): void
    {
        // Sem rollback automático — para recriar a estrutura original consultar
        // `2026_03_30_005001_create_cobranca_itens_extras_table.php`.
    }
};
