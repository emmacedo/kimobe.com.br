<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona a coluna `natureza` para classificar semanticamente o item de
     * cobrança (aluguel, taxa administrativa, outros). Difere da coluna `tipo`
     * — que é a modalidade (recorrente/parcelado/avulso).
     */
    public function up(): void
    {
        Schema::table('itens_cobranca', function (Blueprint $table) {
            $table->enum('natureza', ['aluguel', 'taxa_admin', 'outros'])
                ->default('outros')
                ->after('descricao');
        });

        // Backfill: itens com descricao='Aluguel' viram aluguel; demais ficam em 'outros' (default).
        DB::table('itens_cobranca')
            ->where('descricao', 'Aluguel')
            ->update(['natureza' => 'aluguel']);
    }

    public function down(): void
    {
        Schema::table('itens_cobranca', function (Blueprint $table) {
            $table->dropColumn('natureza');
        });
    }
};
