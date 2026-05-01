<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop dos modelos legados de billing (Kicol → Tenant) substituídos pelo
 * catálogo central FullFlow:
 *   - planos (App\Models\Plano)
 *   - faturas_kimobe (App\Models\FaturaKimobe — fatura interna manual)
 *
 * NÃO confundir com os modelos de cobrança Kimobe → Locatário (cobrancas,
 * cobranca_comprovantes, cobranca_itens_extras, repasses, garantias). Esses
 * são domínio do negócio do cliente e permanecem intactos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'plano_id')) {
                // FK pode ter constraint nomeada — drop seguro
                try {
                    $table->dropForeign(['plano_id']);
                } catch (Throwable $e) {
                    // FK pode não existir; ignorar
                }
                $table->dropColumn('plano_id');
            }
            if (Schema::hasColumn('tenants', 'plano')) {
                $table->dropColumn('plano');
            }
        });

        Schema::dropIfExists('faturas_kimobe');
        Schema::dropIfExists('planos');
    }

    public function down(): void
    {
        // Drop unidirecional — assinatura SaaS agora é exclusivamente FullFlow.
    }
};
