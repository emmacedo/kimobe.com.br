<?php

use App\Models\FullFlowSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculo;
use Kicol\FullFlow\Facades\FullFlow;
use Kicol\FullFlow\Models\FullFlowPlan;

function makePlanoTenantUser(): array
{
    $plan = FullFlowPlan::create([
        'code' => 'kimobe_profissional',
        'name' => 'Profissional',
        'amount' => 129.90,
        'billing_cycle' => 'mensal',
        'trial_days' => 14,
        'sort_order' => 2,
    ]);

    $tenant = Tenant::factory()->create([
        'is_exempt_from_subscription' => false,
        'tipo_documento' => 'cnpj',
        'documento' => '12345678000190',
        'legal_name' => 'Imobiliária X LTDA',
        'nome' => 'Imobiliária X',
        'email_contato' => 'contato@x.com',
    ]);

    $user = User::factory()->create();
    Vinculo::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'papel' => 'admin',
        'status' => 'ativo',
    ]);

    return compact('plan', 'tenant', 'user');
}

test('subscribe redireciona para settings.plano após contratar plano', function () {
    ['plan' => $plan, 'tenant' => $tenant, 'user' => $user] = makePlanoTenantUser();

    FullFlow::shouldReceive('createSubscription')
        ->once()
        ->andReturn([
            'assinatura_id' => 'ff_'.uniqid(),
            'status' => 'trial',
            'trial_ate' => '2026-05-15',
        ]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/settings/plano/contratar', [
            'plan_code' => $plan->code,
            'accept_auto_upgrade' => '1',
        ]);

    $response->assertRedirect(route('settings.plano'));
    expect(FullFlowSubscription::where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

test('changePlan redireciona para settings.plano após upgrade', function () {
    ['plan' => $plan, 'tenant' => $tenant, 'user' => $user] = makePlanoTenantUser();

    $business = FullFlowPlan::create([
        'code' => 'kimobe_business',
        'name' => 'Business',
        'amount' => 299.90,
        'billing_cycle' => 'mensal',
        'trial_days' => 0,
        'sort_order' => 3,
    ]);

    FullFlowSubscription::create([
        'tenant_id' => $tenant->id,
        'fullflow_id' => 'ff_existing',
        'reference' => 'kimobe_tenant_'.$tenant->id,
        'plan_code' => $plan->code,
        'status' => 'ativa',
        'amount' => $plan->amount,
        'billing_cycle' => 'mensal',
    ]);

    FullFlow::shouldReceive('upgradeSubscription')
        ->once()
        ->andReturn([
            'plan_code' => $business->code,
            'amount' => $business->amount,
        ]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/settings/plano/mudar', [
            'plan_code' => $business->code,
            'motivo' => 'Mais imóveis',
        ]);

    $response->assertRedirect(route('settings.plano'));
});

test('cancel redireciona para settings.plano', function () {
    ['plan' => $plan, 'tenant' => $tenant, 'user' => $user] = makePlanoTenantUser();

    FullFlowSubscription::create([
        'tenant_id' => $tenant->id,
        'fullflow_id' => 'ff_to_cancel',
        'reference' => 'kimobe_tenant_'.$tenant->id,
        'plan_code' => $plan->code,
        'status' => 'ativa',
        'amount' => $plan->amount,
        'billing_cycle' => 'mensal',
    ]);

    FullFlow::shouldReceive('cancelSubscription')
        ->once()
        ->andReturn(['status' => 'cancelada']);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/settings/plano/cancelar', [
            'motivo' => 'Trocando de sistema',
            'confirmacao' => '1',
        ]);

    $response->assertRedirect(route('settings.plano'));
});
