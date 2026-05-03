<?php

use App\Models\Fatura;
use App\Models\Repasse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unifica `cobranca_comprovantes` e `repasse_comprovantes` em uma única
 * tabela polimórfica `comprovantes` (Seção 2.11 do escopo). Backfill copia
 * todos os registros existentes preservando timestamps, e em seguida
 * dropa as tabelas antigas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Cria a tabela polimórfica
        Schema::create('comprovantes', function (Blueprint $table) {
            $table->id()->comment('Identificador único do comprovante');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono deste comprovante');

            $table->morphs('owner');
            // owner_type / owner_id apontam para Fatura, Repasse ou ItemCobranca

            $table->enum('tipo', [
                'pagamento_pix',
                'pagamento_boleto',
                'transferencia',
                'recibo',
                'nota_fiscal',
                'outro',
            ])->default('outro')
                ->comment('Categoria do comprovante para filtros e exibição');

            $table->string('arquivo', 500)
                ->comment('Path no storage (ex: comprovantes/faturas/456/recibo.pdf)');

            $table->string('nome_original', 255)
                ->comment('Nome do arquivo no momento do upload');

            $table->string('mime_type', 100)
                ->comment('Tipo MIME (application/pdf, image/jpeg, etc.) para validação');

            $table->unsignedBigInteger('tamanho_bytes')
                ->comment('Tamanho do arquivo em bytes para controle de quota');

            $table->decimal('valor', 10, 2)
                ->nullable()
                ->comment('Valor monetário comprovado, quando aplicável');

            $table->date('data_referencia')
                ->nullable()
                ->comment('Data efetiva do pagamento comprovado');

            $table->text('observacoes')
                ->nullable()
                ->comment('Notas livres sobre o comprovante');

            $table->foreignId('enviado_por_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Usuário que fez o upload');

            $table->enum('enviado_por_papel', ['admin', 'proprietario', 'inquilino'])
                ->nullable()
                ->comment('Papel do usuário no momento do upload, útil para fluxo de revisão');

            $table->timestamps();
        });

        // 2. Backfill: cobranca_comprovantes → comprovantes (owner=Fatura)
        if (Schema::hasTable('cobranca_comprovantes')) {
            DB::table('cobranca_comprovantes')->orderBy('id')->chunk(500, function ($linhas) {
                $rows = $linhas->map(fn ($r) => [
                    'tenant_id' => $r->tenant_id,
                    'owner_type' => Fatura::class,
                    'owner_id' => $r->cobranca_id,
                    'tipo' => 'outro',
                    'arquivo' => $r->caminho,
                    'nome_original' => $r->nome_arquivo,
                    'mime_type' => $r->mime_type,
                    'tamanho_bytes' => $r->tamanho_bytes,
                    'valor' => null,
                    'data_referencia' => null,
                    'observacoes' => $r->observacoes,
                    'enviado_por_user_id' => $r->uploaded_by_user_id ?? null,
                    'enviado_por_papel' => null,
                    'created_at' => $r->created_at,
                    'updated_at' => $r->updated_at,
                ])->all();

                if (! empty($rows)) {
                    DB::table('comprovantes')->insert($rows);
                }
            });
        }

        // 3. Backfill: repasse_comprovantes → comprovantes (owner=Repasse)
        if (Schema::hasTable('repasse_comprovantes')) {
            DB::table('repasse_comprovantes')->orderBy('id')->chunk(500, function ($linhas) {
                $rows = $linhas->map(fn ($r) => [
                    'tenant_id' => $r->tenant_id,
                    'owner_type' => Repasse::class,
                    'owner_id' => $r->repasse_id,
                    'tipo' => 'transferencia',
                    'arquivo' => $r->caminho,
                    'nome_original' => $r->nome_arquivo,
                    'mime_type' => $r->mime_type,
                    'tamanho_bytes' => $r->tamanho_bytes,
                    'valor' => null,
                    'data_referencia' => null,
                    'observacoes' => $r->observacoes,
                    'enviado_por_user_id' => null,
                    'enviado_por_papel' => 'admin',
                    'created_at' => $r->created_at,
                    'updated_at' => $r->updated_at,
                ])->all();

                if (! empty($rows)) {
                    DB::table('comprovantes')->insert($rows);
                }
            });
        }

        // 4. Drop tabelas antigas
        Schema::dropIfExists('cobranca_comprovantes');
        Schema::dropIfExists('repasse_comprovantes');
    }

    public function down(): void
    {
        // Sem rollback automático — para recriar consultar
        // 2026_03_30_005002_create_cobranca_comprovantes_table.php
        // 2026_03_30_005004_create_repasse_comprovantes_table.php
        Schema::dropIfExists('comprovantes');
    }
};
