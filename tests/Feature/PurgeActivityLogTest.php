<?php

use Carbon\CarbonImmutable;
use Spatie\Activitylog\Models\Activity;

it('remove apenas registros mais antigos que --anos', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);

    $antigo = Activity::create([
        'log_name' => 'default',
        'description' => 'antigo',
        'created_at' => CarbonImmutable::now()->subYears(6),
        'updated_at' => CarbonImmutable::now()->subYears(6),
    ]);

    $recente = Activity::create([
        'log_name' => 'default',
        'description' => 'recente',
        'created_at' => CarbonImmutable::now()->subYears(2),
        'updated_at' => CarbonImmutable::now()->subYears(2),
    ]);

    $antesTotal = Activity::count();
    expect($antesTotal)->toBeGreaterThanOrEqual(2);

    $this->artisan('activitylog:purge', ['--anos' => 5])
        ->assertSuccessful();

    expect(Activity::find($antigo->id))->toBeNull();
    expect(Activity::find($recente->id))->not->toBeNull();
});

it('respeita --anos personalizado', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);

    $umAnoMeio = Activity::create([
        'log_name' => 'default',
        'description' => 'um-ano-meio',
        'created_at' => CarbonImmutable::now()->subMonths(18),
        'updated_at' => CarbonImmutable::now()->subMonths(18),
    ]);

    $this->artisan('activitylog:purge', ['--anos' => 1])
        ->assertSuccessful();

    expect(Activity::find($umAnoMeio->id))->toBeNull();
});

it('rejeita --anos inválido', function () {
    $this->artisan('activitylog:purge', ['--anos' => 0])
        ->assertFailed();
});

it('reporta zero quando não há registros antigos', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);

    Activity::query()->delete();

    $this->artisan('activitylog:purge', ['--anos' => 5])
        ->expectsOutputToContain('Nenhum registro')
        ->assertSuccessful();
});
