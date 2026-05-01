<?php

use App\Mail\Billing\AutoUpgradePerformed;
use App\Mail\Billing\TopPlanOverageNotice;
use App\Models\AutoUpgradeLog;
use App\Models\FullFlowSubscription;
use App\Models\QuotaAlertSent;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\Billing\AutoUpgradeResult;
use App\Services\Billing\AutoUpgradeService;
use Illuminate\Support\Facades\Mail;
use Kicol\FullFlow\Exceptions\FullFlowException;
use Kicol\FullFlow\Facades\FullFlow;
use Kicol\FullFlow\Models\FullFlowModule;
use Kicol\FullFlow\Models\FullFlowPlan;

function seedKimobeStarter(): array
{
    $gestao = FullFlowModule::create(['slug' => 'gestao_imoveis', 'label' => 'Gestão', 'type' => 'boolean', 'visible_to_client' => true]);
    $imoveis = FullFlowModule::create(['slug' => 'imoveis', 'label' => 'Imóveis', 'type' => 'quantity', 'visible_to_client' => true]);

    $plan = FullFlowPlan::create([
        'code' => 'kimobe_starter',
        'name' => 'Starter',
        'amount' => 39.90,
        'billing_cycle' => 'mensal',
        'trial_days' => 14,
        'sort_order' => 1,
    ]);
    $plan->modules()->attach($gestao->id);
    $plan->modules()->attach($imoveis->id, ['quota_value' => 3]);

    return ['plan' => $plan, 'gestao' => $gestao, 'imoveis' => $imoveis];
}

function seedKimobeProfissional(array $base): FullFlowPlan
{
    $plan = FullFlowPlan::create([
        'code' => 'kimobe_profissional',
        'name' => 'Profissional',
        'amount' => 129.90,
        'billing_cycle' => 'mensal',
        'trial_days' => 14,
        'sort_order' => 2,
    ]);
    $plan->modules()->attach($base['gestao']->id);
    $plan->modules()->attach($base['imoveis']->id, ['quota_value' => 50]);

    return $plan;
}

function makeTenantWithSub(string $planCode, float $amount = 39.90): Tenant
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    Vinculo::create([
        'tenant_id' => $tenant->id,
        'user_id' => $admin->id,
        'papel' => 'admin',
        'status' => 'ativo',
    ]);
    FullFlowSubscription::create([
        'tenant_id' => $tenant->id,
        'fullflow_id' => 'ff_'.uniqid(),
        'reference' => 'kimobe_tenant_'.$tenant->id,
        'plan_code' => $planCode,
        'status' => 'ativa',
        'amount' => $amount,
        'billing_cycle' => 'mensal',
        'current_period_start' => now()->startOfMonth(),
        'current_period_end' => now()->endOfMonth(),
    ]);

    return $tenant;
}

beforeEach(function () {
    Mail::fake();
});

test('tenant isento sempre passa sem registrar log', function () {
    $tenant = Tenant::factory()->create(['is_exempt_from_subscription' => true]);

    $result = app(AutoUpgradeService::class)->ensureCapacityFor($tenant, 'imoveis', 1);

    expect($result->isAllowed())->toBeTrue()
        ->and($result->resultType)->toBe(AutoUpgradeResult::OK);
    expect(AutoUpgradeLog::where('tenant_id', $tenant->id)->count())->toBe(0);
});

test('sem assinatura retorna failed e bloqueia', function () {
    $tenant = Tenant::factory()->create(['is_exempt_from_subscription' => false]);

    $result = app(AutoUpgradeService::class)->ensureCapacityFor($tenant, 'imoveis', 1);

    expect($result->isAllowed())->toBeFalse()
        ->and($result->resultType)->toBe(AutoUpgradeResult::FAILED);
});

test('plano cobre quota numerica retorna ok', function () {
    seedKimobeStarter();
    $tenant = makeTenantWithSub('kimobe_starter');

    $result = app(AutoUpgradeService::class)->ensureCapacityFor($tenant, 'imoveis', 1);

    expect($result->resultType)->toBe(AutoUpgradeResult::OK);
});

test('quota estourada com auto_upgrade_enabled=false retorna skipped_disabled', function () {
    seedKimobeStarter();
    $tenant = makeTenantWithSub('kimobe_starter');
    $tenant->update(['auto_upgrade_enabled' => false]);

    $result = app(AutoUpgradeService::class)->ensureCapacityFor($tenant, 'imoveis', 4);

    expect($result->isAllowed())->toBeFalse()
        ->and($result->resultType)->toBe(AutoUpgradeResult::SKIPPED_DISABLED);
});

test('quota estourada sem plano superior retorna overage_top_plan e libera', function () {
    $base = seedKimobeStarter();
    seedKimobeProfissional($base);
    $tenant = makeTenantWithSub('kimobe_profissional', 129.90);

    // Pro tem 50 imóveis; pedimos 51 → não há plano superior no test
    $result = app(AutoUpgradeService::class)->ensureCapacityFor($tenant, 'imoveis', 51);

    expect($result->isAllowed())->toBeTrue()
        ->and($result->resultType)->toBe(AutoUpgradeResult::OVERAGE_TOP_PLAN);

    Mail::assertSent(TopPlanOverageNotice::class);
});

test('upgrade automatico chama FullFlow API e atualiza sub local', function () {
    $base = seedKimobeStarter();
    seedKimobeProfissional($base);
    $tenant = makeTenantWithSub('kimobe_starter');

    FullFlow::shouldReceive('upgradeSubscription')
        ->once()
        ->andReturn([
            'plan_code' => 'kimobe_profissional',
            'amount' => 129.90,
            'proration_amount' => 25.00,
        ]);

    $result = app(AutoUpgradeService::class)->ensureCapacityFor($tenant, 'imoveis', 4);

    expect($result->isAllowed())->toBeTrue()
        ->and($result->resultType)->toBe(AutoUpgradeResult::UPGRADED)
        ->and($result->newPlanCode)->toBe('kimobe_profissional')
        ->and($result->prorationAmount)->toBe(25.00);

    $sub = FullFlowSubscription::where('tenant_id', $tenant->id)->first();
    expect($sub->plan_code)->toBe('kimobe_profissional');

    Mail::assertSent(AutoUpgradePerformed::class);
});

test('falha na FullFlow API retorna failed e nao atualiza sub', function () {
    $base = seedKimobeStarter();
    seedKimobeProfissional($base);
    $tenant = makeTenantWithSub('kimobe_starter');

    FullFlow::shouldReceive('upgradeSubscription')
        ->once()
        ->andThrow(new FullFlowException('API timeout'));

    $result = app(AutoUpgradeService::class)->ensureCapacityFor($tenant, 'imoveis', 4);

    expect($result->isAllowed())->toBeFalse()
        ->and($result->resultType)->toBe(AutoUpgradeResult::FAILED);

    $sub = FullFlowSubscription::where('tenant_id', $tenant->id)->first();
    expect($sub->plan_code)->toBe('kimobe_starter');
});

test('debouncing OVERAGE_TOP_PLAN registra QuotaAlertSent uma vez por ciclo', function () {
    $base = seedKimobeStarter();
    seedKimobeProfissional($base);
    $tenant = makeTenantWithSub('kimobe_profissional', 129.90);

    app(AutoUpgradeService::class)->ensureCapacityFor($tenant, 'imoveis', 51);
    app(AutoUpgradeService::class)->ensureCapacityFor($tenant, 'imoveis', 51);
    app(AutoUpgradeService::class)->ensureCapacityFor($tenant, 'imoveis', 51);

    expect(QuotaAlertSent::where('tenant_id', $tenant->id)->where('threshold', 999)->count())->toBe(1);
});
