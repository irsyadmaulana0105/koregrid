<?php

use App\Http\TrustedProxyList;

return [
    /*
     | Proxy (IP atau CIDR, dipisah koma) yang boleh menentukan IP dan skema klien lewat header X-Forwarded-*.
     | Kosong = tidak ada proxy yang dipercaya. Nilai "*" ditolak. Dibaca oleh middleware TrustProxies bawaan Laravel.
     */
    'proxies' => TrustedProxyList::parse((string) env('TRUSTED_PROXIES', '')),
];
