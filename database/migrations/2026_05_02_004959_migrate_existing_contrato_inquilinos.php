<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Popula contrato_inquilinos para contratos existentes — copia o inquilino
     * referenciado em contratos.inquilino_vinculo_id como o "principal".
     */
    public function up(): void
    {
        DB::table('contratos')
            ->whereNotNull('inquilino_vinculo_id')
            ->orderBy('id')
            ->chunkById(500, function ($contratos) {
                $linhas = $contratos->map(fn ($c) => [
                    'tenant_id' => $c->tenant_id,
                    'contrato_id' => $c->id,
                    'vinculo_id' => $c->inquilino_vinculo_id,
                    'principal' => true,
                    'created_at' => $c->created_at ?? now(),
                    'updated_at' => $c->updated_at ?? now(),
                ])->toArray();

                if (! empty($linhas)) {
                    DB::table('contrato_inquilinos')->insert($linhas);
                }
            });
    }

    public function down(): void
    {
        // Irreversível por segurança: depois que esta migration roda, novos co-inquilinos
        // podem ter sido cadastrados e principais podem ter sido alterados. Não há como
        // distinguir os registros gerados aqui dos cadastrados pelo usuário sem perder dados.
        // Para reverter a feature em desenvolvimento, dropar a tabela inteira via
        // `migrate:rollback --step=1` na migration anterior (create_contrato_inquilinos_table).
    }
};
