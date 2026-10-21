<?php

use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('sets the hardening headers on web responses', function () {
    $response = $this->get('/login')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeader('Permissions-Policy');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'");
});

it('adds HSTS only on secure requests', function () {
    $this->get('https://localhost/login')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

    $this->get('http://localhost/login')
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('generates a request id when the client sends none', function () {
    $id = $this->get('/login')->headers->get('X-Request-Id');

    expect(Str::isUuid((string) $id))->toBeTrue();
});

it('keeps a valid client request id and normalizes it to lower case', function () {
    $id = (string) Str::uuid();

    $this->get('/login', ['X-Request-Id' => strtoupper($id)])
        ->assertHeader('X-Request-Id', $id);
});

it('replaces an invalid client request id', function () {
    $id = $this->get('/login', ['X-Request-Id' => 'bukan-uuid <script>'])->headers->get('X-Request-Id');

    expect(Str::isUuid((string) $id))->toBeTrue()
        ->and($id)->not->toContain('script');
});
