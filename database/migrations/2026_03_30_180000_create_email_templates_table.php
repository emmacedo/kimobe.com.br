<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id()->comment('Identificador único do template de email');
            $table->enum('modulo', ['kimobe', 'admin'])->comment('Módulo ao qual pertence: kimobe para emails da plataforma ao assinante, admin para emails do administrador ao proprietário/inquilino');
            $table->string('chave', 100)->unique()->comment('Identificador único do template (ex: kimobe.boas_vindas, admin.cobranca_gerada)');
            $table->string('nome', 255)->comment('Nome legível do template para exibição no editor');
            $table->text('descricao')->nullable()->comment('Descrição do propósito e momento de envio deste template para orientar o editor');
            $table->string('assunto', 255)->comment('Assunto do email, pode conter variáveis como {{nome}}, {{referencia}}');
            $table->text('corpo_html')->comment('Corpo do email em HTML, suporta variáveis como {{nome}}, {{valor}}, {{link}}');
            $table->text('corpo_texto')->nullable()->comment('Versão plain text do email para clientes que não renderizam HTML');
            $table->json('variaveis_disponiveis')->comment('Lista de variáveis que podem ser usadas neste template com descrição de cada');
            $table->boolean('ativo')->default(true)->comment('Se desativado, o email não será enviado mesmo quando o evento ocorrer');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
