<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('plano_id')
                ->nullable()
                ->after('status')
                ->constrained('planos')
                ->nullOnDelete()
                ->comment('Plano de assinatura atual do tenant, referência à tabela planos');

            $table->boolean('cortesia')
                ->default(false)
                ->after('plano_id')
                ->comment('Indica que este assinante é parceiro e não paga pelo uso do sistema, faturas não são geradas');

            $table->string('motivo_cortesia', 255)
                ->nullable()
                ->after('cortesia')
                ->comment('Motivo da cortesia para registro interno (ex: Parceiro fundador, Acordo comercial, Teste piloto)');

            $table->datetime('bloqueado_em')
                ->nullable()
                ->after('motivo_cortesia')
                ->comment('Data e hora em que o acesso foi bloqueado por inadimplência, null quando acesso está liberado');

            $table->string('motivo_bloqueio', 255)
                ->nullable()
                ->after('bloqueado_em')
                ->comment('Motivo do bloqueio exibido ao assinante na tela de bloqueio');
        });

        // Adicionar 'bloqueado' ao enum status (MySQL específico, SQLite aceita qualquer valor em colunas TEXT)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tenants MODIFY COLUMN status ENUM('ativo', 'suspenso', 'bloqueado', 'cancelado') DEFAULT 'ativo'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tenants MODIFY COLUMN status ENUM('ativo', 'suspenso', 'cancelado') DEFAULT 'ativo'");
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plano_id']);
            $table->dropColumn(['plano_id', 'cortesia', 'motivo_cortesia', 'bloqueado_em', 'motivo_bloqueio']);
        });
    }
};
