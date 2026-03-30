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
        Schema::create('imoveis', function (Blueprint $table) {
            $table->id()->comment('Identificador único do imóvel');

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->comment('Assinante dono deste cadastro de imóvel');

            // Endereço
            $table->string('cep', 9)
                ->comment('CEP do imóvel, formato 00000-000, usado para auto-preenchimento do endereço via ViaCEP');

            $table->string('logradouro', 255)
                ->comment('Rua, avenida ou via do imóvel, preenchido automaticamente pelo CEP');

            $table->string('numero', 20)
                ->comment('Número do imóvel no logradouro');

            $table->string('complemento', 255)
                ->nullable()
                ->comment('Apartamento, sala, bloco ou outra identificação complementar');

            $table->string('bairro', 255)
                ->comment('Bairro do imóvel, preenchido automaticamente pelo CEP');

            $table->string('cidade', 255)
                ->comment('Cidade do imóvel, preenchido automaticamente pelo CEP');

            $table->char('uf', 2)
                ->comment('Sigla do estado (ex: SP, RJ), preenchido automaticamente pelo CEP');

            // Características
            $table->enum('tipo', ['apartamento', 'casa', 'sala', 'loja', 'galpao'])
                ->comment('Categoria do imóvel para classificação e filtros');

            $table->enum('status', ['disponivel', 'alugado', 'manutencao', 'inativo'])
                ->default('disponivel')
                ->comment('Situação atual: disponível para locação, alugado com contrato ativo, em manutenção temporária, ou inativo/retirado da carteira');

            $table->unsignedTinyInteger('quartos')
                ->nullable()
                ->comment('Quantidade de quartos (não se aplica a salas e galpões)');

            $table->unsignedTinyInteger('suites')
                ->nullable()
                ->comment('Quantidade de suítes incluídas nos quartos');

            $table->unsignedTinyInteger('banheiros')
                ->nullable()
                ->comment('Quantidade total de banheiros');

            $table->unsignedTinyInteger('vagas_garagem')
                ->nullable()
                ->comment('Quantidade de vagas de garagem ou estacionamento');

            $table->unsignedTinyInteger('andar')
                ->nullable()
                ->comment('Andar do imóvel no edifício (não se aplica a casas)');

            $table->decimal('area_m2', 8, 2)
                ->nullable()
                ->comment('Área total do imóvel em metros quadrados');

            $table->decimal('valor_aluguel_sugerido', 10, 2)
                ->nullable()
                ->comment('Valor de referência para locação quando o imóvel está disponível, o valor real é definido no contrato');

            $table->text('observacoes')
                ->nullable()
                ->comment('Notas internas do administrador sobre o imóvel, não visível para inquilinos');

            $table->timestamps();
            $table->softDeletes()->comment('Data de exclusão lógica — imóvel removido mas preservado para histórico de contratos');

            // Índices compostos para consultas frequentes
            $table->index(['tenant_id', 'status'], 'imoveis_tenant_status_index');
            $table->index(['tenant_id', 'cidade', 'bairro'], 'imoveis_tenant_cidade_bairro_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imoveis');
    }
};
