<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\ConfiguracaoCobrancaKimobe;
use App\Models\Plano;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        AdminUser::create([
            'nome' => 'Admin Kimobe',
            'email' => 'admin@kimobe.com.br',
            'senha_hash' => Hash::make('password'),
        ]);

        // Planos
        $starter = Plano::create([
            'nome' => 'Starter',
            'descricao' => 'Ideal para proprietários diretos com poucos imóveis.',
            'limite_imoveis' => 15,
            'valor_mensal' => 49.90,
            'status' => 'ativo',
            'ordem' => 1,
        ]);

        $profissional = Plano::create([
            'nome' => 'Profissional',
            'descricao' => 'Para imobiliárias em crescimento com carteira média.',
            'limite_imoveis' => 50,
            'valor_mensal' => 129.90,
            'status' => 'ativo',
            'ordem' => 2,
        ]);

        Plano::create([
            'nome' => 'Business',
            'descricao' => 'Para imobiliárias consolidadas com grande carteira.',
            'limite_imoveis' => 200,
            'valor_mensal' => 349.90,
            'status' => 'ativo',
            'ordem' => 3,
        ]);

        Plano::create([
            'nome' => 'Enterprise',
            'descricao' => 'Plano ilimitado com suporte premium e funcionalidades exclusivas.',
            'limite_imoveis' => 0,
            'valor_mensal' => 899.90,
            'status' => 'ativo',
            'ordem' => 4,
        ]);

        // Configuração de cobrança
        ConfiguracaoCobrancaKimobe::create([
            'dias_aviso_antes_vencimento' => 5,
            'aviso_no_dia_vencimento' => true,
            'dias_graca_apos_vencimento' => 7,
            'dias_aviso_bloqueio' => 3,
            'aviso_ao_bloquear' => true,
            'dia_vencimento_fatura' => 10,
        ]);

        // Vincular tenants existentes a planos
        $horizonte = Tenant::where('nome', 'Imobiliária Horizonte')->first();
        if ($horizonte) {
            $horizonte->update(['plano_id' => $profissional->id, 'cortesia' => false]);
        }

        $paulista = Tenant::where('nome', 'Imobiliária Paulista')->first();
        if ($paulista) {
            $paulista->update(['plano_id' => $starter->id, 'cortesia' => false]);
        }

        $carlos = Tenant::where('nome', 'Carlos Mendes Imóveis')->first();
        if ($carlos) {
            $carlos->update([
                'plano_id' => $starter->id,
                'cortesia' => true,
                'motivo_cortesia' => 'Parceiro fundador — período de testes',
            ]);
        }
    }
}
