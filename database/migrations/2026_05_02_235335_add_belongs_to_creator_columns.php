<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frente 3 (Item 11) — Camada 1 da auditoria: autoria direta.
 *
 * Adiciona `criado_por_user_id` e `atualizado_por_user_id` em todas as tabelas
 * de domínio que registram ação humana relevante. Preparação multi-usuário:
 * mesmo no sistema mono-usuário atual, capturar quem criou/editou desde já.
 *
 * Tabelas que já possuem campos contextuais cobrindo ficam de fora:
 *  - `faturas` (gerada_por_user_id, baixada_por_user_id)
 *  - `repasses` (gerado_por_user_id, realizado_por_user_id)
 *  - `itens_cobranca` (criado_por_user_id, atualizado_por_user_id já criados no item 2)
 *  - `comprovantes` (enviado_por_user_id já criado no item 7)
 */
return new class extends Migration
{
    private array $tabelas = [
        'entidades_externas',
        'garantias',
        'fiadores',
        'contratos',
        'imoveis',
        'vinculos',
        'titularidades',
    ];

    public function up(): void
    {
        foreach ($this->tabelas as $nome) {
            Schema::table($nome, function (Blueprint $table) {
                $table->foreignId('criado_por_user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->comment('Operador que criou o registro — preparação multi-usuário');

                $table->foreignId('atualizado_por_user_id')
                    ->nullable()
                    ->after('criado_por_user_id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->comment('Operador da última atualização do registro');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tabelas as $nome) {
            Schema::table($nome, function (Blueprint $table) {
                $table->dropForeign(['criado_por_user_id']);
                $table->dropForeign(['atualizado_por_user_id']);
                $table->dropColumn(['criado_por_user_id', 'atualizado_por_user_id']);
            });
        }
    }
};
