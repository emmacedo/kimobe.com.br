<?php

use App\Models\EntidadeExterna;
use App\Services\TenantService;
use Spatie\Activitylog\Models\Activity;

it('registra atividade ao criar entidade externa', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);
    app(TenantService::class)->setTenant($tenant);

    EntidadeExterna::create([
        'nome' => 'Imodata',
        'tipo' => 'administradora_condominio',
    ]);

    $activity = Activity::query()
        ->where('subject_type', EntidadeExterna::class)
        ->orderByDesc('id')
        ->first();
    expect($activity)->not->toBeNull();
    expect($activity->subject_type)->toBe(EntidadeExterna::class);
    expect($activity->causer_id)->toBe($user->id);
    expect($activity->event)->toBe('created');
});

it('registra atividade ao atualizar entidade externa apenas em campos rastreados', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);
    app(TenantService::class)->setTenant($tenant);

    $ent = EntidadeExterna::create([
        'nome' => 'Original',
        'tipo' => 'administradora_condominio',
    ]);

    $countBefore = Activity::where('subject_type', EntidadeExterna::class)->count();

    $ent->update(['nome' => 'Renomeado', 'cidade' => 'Niterói']);

    $countAfter = Activity::where('subject_type', EntidadeExterna::class)->count();
    expect($countAfter)->toBeGreaterThan($countBefore);

    $latest = Activity::query()
        ->where('subject_type', EntidadeExterna::class)
        ->orderByDesc('id')
        ->first();
    expect($latest->event)->toBe('updated');
    $changes = $latest->attribute_changes;
    expect($changes['attributes']['nome'])->toBe('Renomeado');
    expect($changes['old']['nome'])->toBe('Original');
});

it('não registra atividade quando não há mudança real', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);
    app(TenantService::class)->setTenant($tenant);

    $ent = EntidadeExterna::create([
        'nome' => 'Imodata',
        'tipo' => 'administradora_condominio',
    ]);

    $countBefore = Activity::where('subject_type', EntidadeExterna::class)->count();

    // Touch sem mudar nada (re-save com mesmo valor)
    $ent->update(['nome' => 'Imodata']);

    expect(Activity::where('subject_type', EntidadeExterna::class)->count())->toBe($countBefore);
});
