<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_test/client-ip', fn (Request $request) => $request->ip());
});

it('ignores a forged X-Forwarded-For when no proxy is trusted', function () {
    config(['trustedproxy.proxies' => null]);

    $ip = $this->withHeaders(['X-Forwarded-For' => '203.0.113.9'])->get('/_test/client-ip')->getContent();

    expect($ip)->toBe('127.0.0.1');
});

it('honours X-Forwarded-For only from a trusted proxy', function () {
    config(['trustedproxy.proxies' => ['127.0.0.1']]);

    $ip = $this->withHeaders(['X-Forwarded-For' => '203.0.113.9'])->get('/_test/client-ip')->getContent();

    expect($ip)->toBe('203.0.113.9');
});

it('ignores X-Forwarded-For from a caller outside the trusted list', function () {
    config(['trustedproxy.proxies' => ['10.0.0.0/8']]);

    $ip = $this->withHeaders(['X-Forwarded-For' => '203.0.113.9'])->get('/_test/client-ip')->getContent();

    expect($ip)->toBe('127.0.0.1');
});
