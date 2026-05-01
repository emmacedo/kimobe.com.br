<?php

use App\Models\AdminUser;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\RecoveryCode;

function createAdminWithTwoFactor(): AdminUser
{
    $provider = app(TwoFactorAuthenticationProvider::class);
    $secret = $provider->generateSecretKey();

    return AdminUser::factory()->create([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(
            Collection::times(8, fn () => RecoveryCode::generate())->all(),
        )),
        'two_factor_confirmed_at' => now(),
    ]);
}

// ======================================================================
// Login sem 2FA — redireciona para setup
// ======================================================================

test('admin sem 2fa é redirecionado para minha-conta após login', function () {
    $admin = AdminUser::factory()->create();

    $response = $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/admin/dashboard');

    // Mas ao acessar o dashboard, o middleware redireciona para minha-conta
    $response = $this->get('/admin/dashboard');
    $response->assertRedirect('/admin/minha-conta');
});

test('admin sem 2fa pode acessar minha-conta', function () {
    $admin = AdminUser::factory()->create();

    $this->actingAs($admin, 'admin');

    $response = $this->get('/admin/minha-conta');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/minha-conta')
        ->has('twoFactorEnabled')
        ->where('twoFactorEnabled', false)
    );
});

// ======================================================================
// Login com 2FA — fluxo de challenge
// ======================================================================

test('admin com 2fa é redirecionado para challenge ao logar', function () {
    $admin = createAdminWithTwoFactor();

    $response = $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('admin.two-factor.challenge'));

    // Não deve estar autenticado como admin
    $this->assertFalse(auth()->guard('admin')->check());

    // Mas a session deve ter o pending id
    $this->assertEquals($admin->id, session('admin.2fa_pending_id'));
});

test('challenge com código válido autentica o admin', function () {
    $admin = createAdminWithTwoFactor();
    $provider = app(TwoFactorAuthenticationProvider::class);

    // Simular login que armazena pending
    session(['admin.2fa_pending_id' => $admin->id, 'admin.2fa_remember' => false]);

    // Gerar código válido
    $validCode = '123456';
    $this->mock(TwoFactorAuthenticationProvider::class, function ($mock) use ($validCode) {
        $mock->shouldReceive('verify')->andReturnUsing(fn ($secret, $code) => $code === $validCode);
    });

    $response = $this->post('/admin/two-factor-challenge', [
        'code' => $validCode,
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertTrue(auth()->guard('admin')->check());
});

test('challenge com código inválido retorna erro', function () {
    $admin = createAdminWithTwoFactor();

    session(['admin.2fa_pending_id' => $admin->id, 'admin.2fa_remember' => false]);

    $response = $this->post('/admin/two-factor-challenge', [
        'code' => '000000',
    ]);

    $response->assertSessionHasErrors('code');
    $this->assertFalse(auth()->guard('admin')->check());
});

test('challenge com código de recuperação válido autentica o admin', function () {
    $admin = createAdminWithTwoFactor();
    $codes = $admin->recoveryCodes();
    $validCode = $codes[0];

    session(['admin.2fa_pending_id' => $admin->id, 'admin.2fa_remember' => false]);

    $response = $this->post('/admin/two-factor-challenge', [
        'recovery_code' => $validCode,
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertTrue(auth()->guard('admin')->check());

    // O código usado deve ter sido substituído
    $admin->refresh();
    $this->assertNotContains($validCode, $admin->recoveryCodes());
});

test('challenge sem session pending redireciona para login', function () {
    $response = $this->get('/admin/two-factor-challenge');
    $response->assertRedirect(route('admin.login'));
});

// ======================================================================
// Setup do 2FA — enable, confirm, disable
// ======================================================================

test('admin pode ativar 2fa', function () {
    $admin = AdminUser::factory()->create();
    $this->actingAs($admin, 'admin');

    $response = $this->post('/admin/minha-conta/two-factor');
    $response->assertRedirect();

    $admin->refresh();
    $this->assertNotNull($admin->two_factor_secret);
    $this->assertNotNull($admin->two_factor_recovery_codes);
    $this->assertNull($admin->two_factor_confirmed_at);
});

test('admin pode confirmar 2fa com código válido', function () {
    $admin = AdminUser::factory()->create();
    $this->actingAs($admin, 'admin');

    // Ativar primeiro
    $this->post('/admin/minha-conta/two-factor');

    // Mock do provider para aceitar qualquer código
    $this->mock(TwoFactorAuthenticationProvider::class, function ($mock) {
        $mock->shouldReceive('verify')->andReturn(true);
        $mock->shouldReceive('generateSecretKey')->andReturn('test-secret');
    });

    $response = $this->post('/admin/minha-conta/two-factor/confirm', [
        'code' => '123456',
    ]);

    $response->assertRedirect();

    $admin->refresh();
    $this->assertNotNull($admin->two_factor_confirmed_at);
});

test('admin pode desativar 2fa com senha correta', function () {
    $admin = createAdminWithTwoFactor();
    $this->actingAs($admin, 'admin');

    $response = $this->delete('/admin/minha-conta/two-factor', [
        'password' => 'password',
    ]);
    $response->assertRedirect();

    $admin->refresh();
    $this->assertNull($admin->two_factor_secret);
    $this->assertNull($admin->two_factor_recovery_codes);
    $this->assertNull($admin->two_factor_confirmed_at);
});

test('admin não pode desativar 2fa com senha incorreta', function () {
    $admin = createAdminWithTwoFactor();
    $this->actingAs($admin, 'admin');

    $response = $this->delete('/admin/minha-conta/two-factor', [
        'password' => 'wrong-password',
    ]);
    $response->assertSessionHasErrors('password');

    $admin->refresh();
    $this->assertNotNull($admin->two_factor_secret);
});

// ======================================================================
// Middleware AdminRequire2FA
// ======================================================================

test('admin com 2fa ativo pode acessar dashboard', function () {
    $admin = createAdminWithTwoFactor();
    $this->actingAs($admin, 'admin');

    $response = $this->get('/admin/dashboard');
    $response->assertOk();
});

test('admin sem 2fa não pode acessar rotas protegidas', function () {
    $admin = AdminUser::factory()->create();
    $this->actingAs($admin, 'admin');

    // /admin/planos foi descontinuada (catálogo agora é FullFlow); usar rota de assinantes que segue a mesma proteção 2FA.
    $response = $this->get('/admin/assinantes');
    $response->assertRedirect('/admin/minha-conta');
});

// ======================================================================
// Endpoints JSON do 2FA
// ======================================================================

test('admin pode obter qr code antes de confirmar 2fa', function () {
    $admin = AdminUser::factory()->create();
    $this->actingAs($admin, 'admin');

    $this->post('/admin/minha-conta/two-factor');

    $response = $this->getJson('/admin/minha-conta/two-factor/qr-code');
    $response->assertOk();
    $response->assertJsonStructure(['svg', 'url']);
});

test('admin não pode obter qr code após confirmar 2fa', function () {
    $admin = createAdminWithTwoFactor();
    $this->actingAs($admin, 'admin');

    $response = $this->getJson('/admin/minha-conta/two-factor/qr-code');
    $response->assertNotFound();
});

test('admin pode obter recovery codes', function () {
    $admin = createAdminWithTwoFactor();
    $this->actingAs($admin, 'admin');

    $response = $this->getJson('/admin/minha-conta/two-factor/recovery-codes');
    $response->assertOk();
    $response->assertJsonCount(8);
});

test('admin pode regenerar recovery codes', function () {
    $admin = createAdminWithTwoFactor();
    $this->actingAs($admin, 'admin');

    $codesAntes = $admin->recoveryCodes();

    $response = $this->postJson('/admin/minha-conta/two-factor/recovery-codes');
    $response->assertOk();
    $response->assertJsonCount(8);

    $admin->refresh();
    $this->assertNotEquals($codesAntes, $admin->recoveryCodes());
});
