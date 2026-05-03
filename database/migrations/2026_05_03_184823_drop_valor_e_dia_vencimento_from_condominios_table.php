<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('condominios', function (Blueprint $table) {
            $table->dropColumn(['valor', 'dia_vencimento']);
        });
    }

    public function down(): void
    {
        Schema::table('condominios', function (Blueprint $table) {
            $table->unsignedTinyInteger('dia_vencimento')
                ->nullable()
                ->after('entidade_externa_id')
                ->comment('Dia do mês em que a taxa do condomínio vence (1-31)');

            $table->decimal('valor', 10, 2)
                ->nullable()
                ->after('dia_vencimento')
                ->comment('Valor mensal da taxa de condomínio em reais');
        });
    }
};
