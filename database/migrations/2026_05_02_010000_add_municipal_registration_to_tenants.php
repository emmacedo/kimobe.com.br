<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'municipal_registration')) {
                // CCM (Cadastro de Contribuintes Mobiliários). Mais relevante que IE
                // para corretoras de imóveis (serviço sujeito a ISS, não a ICMS).
                $table->string('municipal_registration', 30)->nullable()->after('state_registration');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'municipal_registration')) {
                $table->dropColumn('municipal_registration');
            }
        });
    }
};
