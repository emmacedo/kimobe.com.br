<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id()->comment('Identificador único do log de envio');

            $table->foreignId('template_id')->nullable()->constrained('email_templates')->nullOnDelete()
                ->comment('Template usado para gerar este email, null se o template foi excluído');

            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete()
                ->comment('Tenant relacionado a este envio, null para emails do módulo kimobe sem tenant');

            $table->string('destinatario_email', 255)->comment('Email do destinatário no momento do envio');
            $table->string('destinatario_nome', 255)->nullable()->comment('Nome do destinatário no momento do envio');
            $table->string('assunto', 255)->comment('Assunto efetivo do email após substituição das variáveis');
            $table->string('chave_template', 100)->comment('Chave do template usado, mantém referência mesmo se template for excluído');
            $table->json('variaveis_usadas')->nullable()->comment('Valores das variáveis substituídas neste envio para auditoria');

            $table->enum('status', ['enviado', 'falha', 'pendente'])->default('pendente')
                ->comment('Resultado do envio: enviado com sucesso, falha no envio, pendente na fila');

            $table->text('erro')->nullable()->comment('Mensagem de erro quando o envio falha para diagnóstico');
            $table->datetime('enviado_em')->nullable()->comment('Data e hora em que o email foi efetivamente enviado pelo servidor SMTP');

            $table->boolean('aberto')->default(false)->comment('Indica se o destinatário abriu o email detectado via pixel de rastreamento');
            $table->datetime('aberto_em')->nullable()->comment('Data e hora da primeira abertura do email detectada pelo pixel');
            $table->unsignedInteger('aberturas_count')->default(0)->comment('Quantidade total de vezes que o pixel foi carregado');
            $table->string('ip_abertura', 45)->nullable()->comment('IP do destinatário na primeira abertura para referência');
            $table->string('user_agent_abertura', 500)->nullable()->comment('User agent do cliente de email na primeira abertura');
            $table->string('token_rastreamento', 64)->unique()->comment('Token único usado na URL do pixel de rastreamento para identificar este email');

            $table->timestamps();

            $table->index(['tenant_id', 'chave_template'], 'email_logs_tenant_chave_index');
            $table->index('destinatario_email', 'email_logs_destinatario_index');
            $table->index('status', 'email_logs_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
