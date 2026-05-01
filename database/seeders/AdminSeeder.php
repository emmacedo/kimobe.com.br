<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        AdminUser::create([
            'nome' => 'Admin Kimobe',
            'email' => 'admin@kimobe.com.br',
            'senha_hash' => Hash::make('password'),
        ]);

        // Catálogo de planos é gerenciado no FullFlow (rodar `php artisan fullflow:catalog-sync`).

        // Tenants de exemplo isentos para testes locais.
        Tenant::where('nome', 'Carlos Mendes Imóveis')->update([
            'is_exempt_from_subscription' => true,
            'motivo_isencao' => 'Parceiro fundador — período de testes',
        ]);
    }
}
