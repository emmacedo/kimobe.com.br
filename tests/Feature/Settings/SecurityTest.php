<?php

use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('seguranca page is displayed', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = createUserWithTenant();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.seguranca'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/seguranca')
            ->where('canManageTwoFactor', true)
            ->where('twoFactorEnabled', false),
        );
});

test('seguranca page requires password confirmation when enabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = createUserWithTenant();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('settings.seguranca'));

    $response->assertRedirect(route('password.confirm'));
});

test('seguranca page does not require password confirmation when disabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = createUserWithTenant();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => false,
    ]);

    $this->actingAs($user)
        ->get(route('settings.seguranca'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/seguranca'),
        );
});

test('seguranca page renders without two factor when feature is disabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    config(['fortify.features' => []]);

    $user = createUserWithTenant();

    $this->actingAs($user)
        ->get(route('settings.seguranca'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/seguranca')
            ->where('canManageTwoFactor', false)
            ->missing('twoFactorEnabled')
            ->missing('requiresConfirmation'),
        );
});

test('password can be updated', function () {
    $user = createUserWithTenant();

    $response = $this
        ->actingAs($user)
        ->from(route('settings.seguranca'))
        ->put(route('settings.seguranca.senha'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.seguranca'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = createUserWithTenant();

    $response = $this
        ->actingAs($user)
        ->from(route('settings.seguranca'))
        ->put(route('settings.seguranca.senha'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('settings.seguranca'));
});
