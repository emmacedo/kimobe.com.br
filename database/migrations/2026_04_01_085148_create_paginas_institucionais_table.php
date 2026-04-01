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
        Schema::create('paginas_institucionais', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique()->comment('Identificador da URL da página (ex: termos-de-uso)');
            $table->string('titulo', 255)->comment('Título exibido no topo da página e na tag <title>');
            $table->longText('conteudo')->comment('Conteúdo HTML da página editável pelo super admin');
            $table->string('meta_description', 255)->nullable()->comment('Meta description para SEO');
            $table->foreignId('atualizado_por')->nullable()->constrained('admin_users')->nullOnDelete()->comment('Último admin que editou esta página');
            $table->boolean('publicado')->default(true)->comment('Se falso a página retorna 404 para visitantes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paginas_institucionais');
    }
};
