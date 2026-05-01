<?php

use App\Models\FullFlowSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculo;

function makeUserAttachedToTenant(Tenant $tenant): User
{
    $user = User::factory()->create();
    Vinculo::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'papel' => 'admin',
        'status' => 'ativo',
    ]);

    return $user;
}

test('tenant isento acessa rotas protegidas livremente', function () {
    $tenant = Tenant::factory()->create(['is_exempt_from_subscription' => true]);
    $user = makeUserAttachedToTenant($tenant);

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
        ->get('/dashboard')
        ->assertOk();
});

test('tenant sem assinatura é redirecionado para /settings/plano', function () {
    $tenant = Tenant::factory()->create(['is_exempt_from_subscription' => false]);
    $user = makeUserAttachedToTenant($tenant);

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
        ->get('/dashboard')
        ->assertRedirect(route('settings.plano'));
});

test('tenant com assinatura ativa acessa rotas protegidas', function () {
    $tenant = Tenant::factory()->create(['is_exempt_from_subscription' => false]);
    FullFlowSubscription::create([
        'tenant_id' => $tenant->id,
        'fullflow_id' => 'ff_'.uniqid(),
        'reference' => 'kimobe_tenant_'.$tenant->id,
        'plan_code' => 'kimobe_starter',
        'status' => 'ativa',
        'amount' => 39.90,
        'billing_cycle' => 'mensal',
    ]);
    $user = makeUserAttachedToTenant($tenant);

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
        ->get('/dashboard')
        ->assertOk();
});

test('tenant com assinatura suspensa é redirecionado', function () {
    $tenant = Tenant::factory()->create(['is_exempt_from_subscription' => false]);
    FullFlowSubscription::create([
        'tenant_id' => $tenant->id,
        'fullflow_id' => 'ff_'.uniqid(),
        'reference' => 'kimobe_tenant_'.$tenant->id,
        'plan_code' => 'kimobe_starter',
        'status' => 'suspensa',
        'amount' => 39.90,
        'billing_cycle' => 'mensal',
    ]);
    $user = makeUserAttachedToTenant($tenant);

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
        ->get('/dashboard')
        ->assertRedirect(route('settings.plano'));
});

test('tenant em trial acessa normalmente', function () {
    $tenant = Tenant::factory()->create([
        'is_exempt_from_subscription' => false,
    ]);
    FullFlowSubscription::create([
        'tenant_id' => $tenant->id,
        'fullflow_id' => 'ff_'.uniqid(),
        'reference' => 'kimobe_tenant_'.$tenant->id,
        'plan_code' => 'kimobe_starter',
        'status' => 'trial',
        'trial_until' => now()->addDays(14)->toDateString(),
        'amount' => 39.90,
        'billing_cycle' => 'mensal',
    ]);
    $user = makeUserAttachedToTenant($tenant);

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
        ->get('/dashboard')
        ->assertOk();
});
