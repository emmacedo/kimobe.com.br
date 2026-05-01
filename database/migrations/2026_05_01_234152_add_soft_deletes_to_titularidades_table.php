<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('titularidades', function (Blueprint $table) {
            $table->softDeletes()
                ->comment('Data de exclusão lógica — titular removido mas preservado para histórico de repasses e auditoria');
        });
    }

    public function down(): void
    {
        Schema::table('titularidades', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
