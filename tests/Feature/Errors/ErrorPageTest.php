<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Config::set('app.debug', false);
});

test('404 renderiza página Inertia de erro com status correto', function () {
    $response = $this->get('/rota_que_nao_existe_'.uniqid());

    $response->assertStatus(404);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('errors/error')
        ->where('status', 404)
    );
});

test('403 renderiza página Inertia de erro', function () {
    Route::get('/__test/error/403', fn () => abort(403))->middleware('web');

    $response = $this->get('/__test/error/403');

    $response->assertStatus(403);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('errors/error')
        ->where('status', 403)
    );
});

test('500 renderiza página Inertia de erro', function () {
    Route::get('/__test/error/500', fn () => abort(500))->middleware('web');

    $response = $this->get('/__test/error/500');

    $response->assertStatus(500);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('errors/error')
        ->where('status', 500)
    );
});

test('503 renderiza página Inertia de erro', function () {
    Route::get('/__test/error/503', fn () => abort(503))->middleware('web');

    $response = $this->get('/__test/error/503');

    $response->assertStatus(503);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('errors/error')
        ->where('status', 503)
    );
});

test('quando APP_DEBUG está ativo, página Inertia não substitui a tela padrão de erro', function () {
    Config::set('app.debug', true);

    Route::get('/__test/error/debug', fn () => abort(500))->middleware('web');

    $response = $this->get('/__test/error/debug');

    $response->assertStatus(500);
    expect($response->headers->get('X-Inertia'))->toBeNull();
});

test('419 redireciona para a página anterior com flash de erro', function () {
    // Simula CSRF mismatch via abort direto (mesmo handler aplica)
    Route::get('/__test/error/419', function () {
        abort(419);
    })->middleware('web');

    $response = $this->from('/origem')->get('/__test/error/419');

    $response->assertRedirect('/origem');
    $response->assertSessionHas('error');
});
