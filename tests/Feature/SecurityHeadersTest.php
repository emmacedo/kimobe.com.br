<?php

test('responses include security headers', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
    $response->assertHeader('Content-Security-Policy');
});

test('csp contains required directives', function () {
    $response = $this->get('/');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self'")
        ->toContain("style-src 'self' 'unsafe-inline' https://fonts.bunny.net")
        ->toContain("font-src 'self' https://fonts.bunny.net")
        ->toContain("img-src 'self' data: https://kicol.com.br")
        ->toContain("connect-src 'self' https://viacep.com.br")
        ->toContain("frame-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'");
});

test('hsts header is not present in non-production', function () {
    $response = $this->get('/');

    $response->assertHeaderMissing('Strict-Transport-Security');
});

test('csp does not include unsafe-inline for scripts in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $response = $this->get('/');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("script-src 'self'")
        ->not->toContain("script-src 'self' 'unsafe-inline'");
});

test('hsts header is present in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $response = $this->get('/');

    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
