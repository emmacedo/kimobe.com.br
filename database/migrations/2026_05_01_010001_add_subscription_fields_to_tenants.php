<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Padronização com eBookView: cortesia → is_exempt_from_subscription.
            if (Schema::hasColumn('tenants', 'cortesia') && ! Schema::hasColumn('tenants', 'is_exempt_from_subscription')) {
                $table->renameColumn('cortesia', 'is_exempt_from_subscription');
            }
            if (Schema::hasColumn('tenants', 'motivo_cortesia') && ! Schema::hasColumn('tenants', 'motivo_isencao')) {
                $table->renameColumn('motivo_cortesia', 'motivo_isencao');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'auto_upgrade_enabled')) {
                $table->boolean('auto_upgrade_enabled')->default(true)->after('is_exempt_from_subscription');
            }
            if (! Schema::hasColumn('tenants', 'tipo_documento')) {
                // Identifica PF/PJ — separado do enum `tipo` (imobiliaria/proprietario_direto).
                $table->string('tipo_documento', 8)->default('cpf')->after('documento');
            }
            if (! Schema::hasColumn('tenants', 'legal_name')) {
                // Razão social (PJ).
                $table->string('legal_name')->nullable()->after('nome');
            }
            if (! Schema::hasColumn('tenants', 'state_registration')) {
                // Inscrição Estadual (PJ).
                $table->string('state_registration', 30)->nullable()->after('tipo_documento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach (['legal_name', 'state_registration', 'tipo_documento', 'auto_upgrade_enabled'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'is_exempt_from_subscription') && ! Schema::hasColumn('tenants', 'cortesia')) {
                $table->renameColumn('is_exempt_from_subscription', 'cortesia');
            }
            if (Schema::hasColumn('tenants', 'motivo_isencao') && ! Schema::hasColumn('tenants', 'motivo_cortesia')) {
                $table->renameColumn('motivo_isencao', 'motivo_cortesia');
            }
        });
    }
};
