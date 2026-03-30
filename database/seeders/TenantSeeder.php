<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    /**
     * Cria cenário de teste realista demonstrando:
     * - Tenant imobiliária com múltiplos papéis
     * - Usuário cross-tenant (Eduardo: proprietário no T1, inquilino no T2)
     * - Proprietário direto com papéis acumulados (admin + proprietário)
     */
    public function run(): void
    {
        // === Tenant 1 — Imobiliária Horizonte ===
        $tenant1 = Tenant::create([
            'nome' => 'Imobiliária Horizonte',
            'tipo' => 'imobiliaria',
            'documento' => '12345678000190', // CNPJ fictício
            'plano' => 'profissional',
            'status' => 'ativo',
        ]);

        $marcelo = User::factory()->create([
            'name' => 'Marcelo Rodrigues',
            'email' => 'marcelo@kimobe.test',
            'password' => Hash::make('password'),
        ]);

        $ana = User::factory()->create([
            'name' => 'Ana Costa',
            'email' => 'ana@kimobe.test',
            'password' => Hash::make('password'),
        ]);

        $pedro = User::factory()->create([
            'name' => 'Pedro Lima',
            'email' => 'pedro@kimobe.test',
            'password' => Hash::make('password'),
        ]);

        // Eduardo será reutilizado no tenant 2 (cenário cross-tenant)
        $eduardo = User::factory()->create([
            'name' => 'Eduardo Silva',
            'email' => 'eduardo@kimobe.test',
            'password' => Hash::make('password'),
        ]);

        Vinculo::create(['user_id' => $marcelo->id, 'tenant_id' => $tenant1->id, 'papel' => 'admin', 'status' => 'ativo']);
        Vinculo::create(['user_id' => $ana->id, 'tenant_id' => $tenant1->id, 'papel' => 'proprietario', 'status' => 'ativo']);
        Vinculo::create(['user_id' => $pedro->id, 'tenant_id' => $tenant1->id, 'papel' => 'inquilino', 'status' => 'ativo']);
        Vinculo::create(['user_id' => $eduardo->id, 'tenant_id' => $tenant1->id, 'papel' => 'proprietario', 'status' => 'ativo']);

        // === Tenant 2 — Imobiliária Paulista ===
        $tenant2 = Tenant::create([
            'nome' => 'Imobiliária Paulista',
            'tipo' => 'imobiliaria',
            'documento' => '98765432000110', // CNPJ fictício
            'plano' => 'basico',
            'status' => 'ativo',
        ]);

        $fernanda = User::factory()->create([
            'name' => 'Fernanda Souza',
            'email' => 'fernanda@kimobe.test',
            'password' => Hash::make('password'),
        ]);

        Vinculo::create(['user_id' => $fernanda->id, 'tenant_id' => $tenant2->id, 'papel' => 'admin', 'status' => 'ativo']);

        // Eduardo é proprietário no tenant 1 e inquilino no tenant 2 (cross-tenant)
        Vinculo::create(['user_id' => $eduardo->id, 'tenant_id' => $tenant2->id, 'papel' => 'inquilino', 'status' => 'ativo']);

        // === Tenant 3 — Carlos Mendes Imóveis (proprietário direto) ===
        $tenant3 = Tenant::create([
            'nome' => 'Carlos Mendes Imóveis',
            'tipo' => 'proprietario_direto',
            'documento' => '12345678901', // CPF fictício
            'plano' => 'basico',
            'status' => 'ativo',
        ]);

        $carlos = User::factory()->create([
            'name' => 'Carlos Mendes',
            'email' => 'carlos@kimobe.test',
            'password' => Hash::make('password'),
        ]);

        // Proprietário direto com papéis acumulados: admin + proprietário
        Vinculo::create(['user_id' => $carlos->id, 'tenant_id' => $tenant3->id, 'papel' => 'admin', 'status' => 'ativo']);
        Vinculo::create(['user_id' => $carlos->id, 'tenant_id' => $tenant3->id, 'papel' => 'proprietario', 'status' => 'ativo']);
    }
}
