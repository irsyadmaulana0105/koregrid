<?php

use App\Http\TrustedProxyList;

it('treats an empty list as no trusted proxies', function (string $raw) {
    expect(TrustedProxyList::parse($raw))->toBeNull();
})->with(['empty' => [''], 'spaces' => ['   '], 'commas' => [' , ,']]);

it('parses addresses and CIDR ranges and trims whitespace', function () {
    expect(TrustedProxyList::parse(' 10.0.0.0/8 , 192.168.1.10,2001:db8::/32 '))
        ->toBe(['10.0.0.0/8', '192.168.1.10', '2001:db8::/32']);
});

it('rejects values that would trust any caller', function (string $raw) {
    expect(fn () => TrustedProxyList::parse($raw))->toThrow(InvalidArgumentException::class);
})->with([
    'star' => ['*'],
    'double star' => ['**'],
    'remote addr' => ['REMOTE_ADDR'],
    'star in a list' => ['10.0.0.1,*'],
    'all ipv4' => ['0.0.0.0/0'],
    'all ipv6' => ['::/0'],
]);

it('rejects malformed entries', function (string $raw) {
    expect(fn () => TrustedProxyList::parse($raw))->toThrow(InvalidArgumentException::class);
})->with([
    'hostname' => ['proxy.internal'],
    'bad ip' => ['300.1.1.1'],
    'ipv4 mask too long' => ['10.0.0.0/33'],
    'non numeric mask' => ['10.0.0.0/abc'],
    'ipv6 mask too long' => ['2001:db8::/129'],
]);
