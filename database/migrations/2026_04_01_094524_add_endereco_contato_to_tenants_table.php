<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Endereço da sede
            $table->string('cep', 9)->nullable()->after('motivo_bloqueio')->comment('CEP do endereço da sede da empresa');
            $table->string('logradouro', 255)->nullable()->after('cep')->comment('Logradouro do endereço da sede');
            $table->string('numero', 20)->nullable()->after('logradouro')->comment('Número do endereço da sede');
            $table->string('complemento', 255)->nullable()->after('numero')->comment('Complemento do endereço da sede');
            $table->string('bairro', 255)->nullable()->after('complemento')->comment('Bairro da sede');
            $table->string('cidade', 255)->nullable()->after('bairro')->comment('Cidade da sede');
            $table->char('uf', 2)->nullable()->after('cidade')->comment('Estado da sede (sigla UF)');

            // Contato da empresa
            $table->string('email_contato', 255)->nullable()->after('uf')->comment('Email de contato da empresa para clientes e parceiros');
            $table->string('telefone_comercial', 20)->nullable()->after('email_contato')->comment('Telefone comercial da empresa');
            $table->string('whatsapp', 20)->nullable()->after('telefone_comercial')->comment('Número de WhatsApp da empresa para atendimento');
            $table->string('site', 255)->nullable()->after('whatsapp')->comment('URL do site institucional da empresa');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
                'email_contato', 'telefone_comercial', 'whatsapp', 'site',
            ]);
        });
    }
};
