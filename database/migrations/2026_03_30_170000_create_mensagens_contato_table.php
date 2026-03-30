<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagens_contato', function (Blueprint $table) {
            $table->id()->comment('Identificador único da mensagem de contato');
            $table->string('nome', 255)->comment('Nome de quem enviou a mensagem de contato');
            $table->string('email', 255)->comment('Email de quem enviou para resposta');
            $table->string('telefone', 20)->nullable()->comment('Telefone de contato opcional');
            $table->string('assunto', 100)->comment('Categoria do assunto selecionada no formulário');
            $table->text('mensagem')->comment('Conteúdo da mensagem enviada pelo visitante');
            $table->boolean('lida')->default(false)->comment('Indica se a mensagem já foi lida pela equipe do Kimobe');
            $table->boolean('respondida')->default(false)->comment('Indica se a equipe já respondeu esta mensagem');
            $table->datetime('respondida_em')->nullable()->comment('Data e hora da resposta para controle de SLA');
            $table->string('ip', 45)->nullable()->comment('IP do remetente para controle de spam');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagens_contato');
    }
};
