<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes_cobranca_kimobe', function (Blueprint $table) {
            $table->id()->comment('Identificador único da configuração (sempre 1 registro)');
            $table->unsignedTinyInteger('dias_aviso_antes_vencimento')->default(5)->comment('Quantos dias antes do vencimento enviar o aviso de cobrança por email ao assinante');
            $table->boolean('aviso_no_dia_vencimento')->default(true)->comment('Se deve enviar lembrete por email no dia do vencimento da fatura');
            $table->unsignedTinyInteger('dias_graca_apos_vencimento')->default(7)->comment('Quantos dias após o vencimento o assinante ainda consegue acessar o sistema antes do bloqueio');
            $table->unsignedTinyInteger('dias_aviso_bloqueio')->default(3)->comment('Quantos dias após o vencimento enviar aviso de bloqueio iminente, deve ser menor que dias_graca');
            $table->boolean('aviso_ao_bloquear')->default(true)->comment('Se deve enviar email informando quando o acesso for efetivamente bloqueado por inadimplência');
            $table->unsignedTinyInteger('dia_vencimento_fatura')->default(10)->comment('Dia fixo do mês em que as faturas do Kimobe vencem para todos os assinantes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes_cobranca_kimobe');
    }
};
