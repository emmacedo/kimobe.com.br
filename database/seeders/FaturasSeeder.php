<?php

namespace Database\Seeders;

use App\Models\FaturaKimobe;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class FaturasSeeder extends Seeder
{
    public function run(): void
    {
        $horizonte = Tenant::where('nome', 'Imobiliária Horizonte')->first();
        $paulista = Tenant::where('nome', 'Imobiliária Paulista')->first();

        if (! $horizonte || ! $paulista) {
            return;
        }

        // Imobiliária Horizonte (Profissional R$ 129,90)
        FaturaKimobe::create([
            'tenant_id' => $horizonte->id, 'plano_id' => $horizonte->plano_id,
            'referencia' => '01/2026', 'valor' => 129.90,
            'data_vencimento' => '2026-01-10', 'data_pagamento' => '2026-01-10',
            'metodo_pagamento' => 'pix', 'status' => 'pago',
        ]);
        FaturaKimobe::create([
            'tenant_id' => $horizonte->id, 'plano_id' => $horizonte->plano_id,
            'referencia' => '02/2026', 'valor' => 129.90,
            'data_vencimento' => '2026-02-10', 'data_pagamento' => '2026-02-12',
            'metodo_pagamento' => 'boleto', 'status' => 'pago',
        ]);
        FaturaKimobe::create([
            'tenant_id' => $horizonte->id, 'plano_id' => $horizonte->plano_id,
            'referencia' => '03/2026', 'valor' => 129.90,
            'data_vencimento' => '2026-03-10', 'status' => 'pendente',
        ]);

        // Imobiliária Paulista (Starter R$ 49,90)
        FaturaKimobe::create([
            'tenant_id' => $paulista->id, 'plano_id' => $paulista->plano_id,
            'referencia' => '01/2026', 'valor' => 49.90,
            'data_vencimento' => '2026-01-10', 'data_pagamento' => '2026-01-10',
            'metodo_pagamento' => 'pix', 'status' => 'pago',
        ]);
        FaturaKimobe::create([
            'tenant_id' => $paulista->id, 'plano_id' => $paulista->plano_id,
            'referencia' => '02/2026', 'valor' => 49.90,
            'data_vencimento' => '2026-02-10', 'status' => 'atrasado',
        ]);
        FaturaKimobe::create([
            'tenant_id' => $paulista->id, 'plano_id' => $paulista->plano_id,
            'referencia' => '03/2026', 'valor' => 49.90,
            'data_vencimento' => '2026-03-10', 'status' => 'pendente',
        ]);
    }
}
