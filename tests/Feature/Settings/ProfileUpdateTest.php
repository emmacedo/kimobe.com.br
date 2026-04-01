<?php

test('perfil page is displayed', function () {
    $user = createUserWithTenant();

    $response = $this
        ->actingAs($user)
        ->get(route('settings.perfil'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = createUserWithTenant();

    $response = $this
        ->actingAs($user)
        ->put(route('settings.perfil.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = createUserWithTenant();

    $response = $this
        ->actingAs($user)
        ->put(route('settings.perfil.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});
