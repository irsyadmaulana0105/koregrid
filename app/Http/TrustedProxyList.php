<?php

namespace App\Http;

use InvalidArgumentException;

/**
 * Mengubah nilai TRUSTED_PROXIES (daftar IP/CIDR dipisah koma) menjadi daftar proxy yang dipercaya.
 *
 * Nilai yang berarti "percayai siapa pun yang menghubungi" ditolak keras: dengan nginx yang meneruskan
 * header X-Forwarded-For dari klien apa adanya, itu membuat IP klien (dan kunci throttle login) bisa dipalsukan.
 */
final class TrustedProxyList
{
    private const FORBIDDEN = ['*', '**', 'REMOTE_ADDR'];

    /** @return list<string>|null null = tidak ada proxy yang dipercaya */
    public static function parse(string $raw): ?array
    {
        $entries = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn (string $entry): bool => $entry !== '',
        ));

        if ($entries === []) {
            return null;
        }

        foreach ($entries as $entry) {
            self::assertSafe($entry);
        }

        return $entries;
    }

    private static function assertSafe(string $entry): void
    {
        if (in_array($entry, self::FORBIDDEN, true)) {
            throw new InvalidArgumentException(
                "TRUSTED_PROXIES berisi '{$entry}', yang akan mempercayai siapa pun. Isi dengan IP atau CIDR proxy yang sebenarnya.",
            );
        }

        $parts = explode('/', $entry, 2);
        $ip = $parts[0];
        $mask = $parts[1] ?? null;

        $isV4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $isV6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        if (! $isV4 && ! $isV6) {
            throw new InvalidArgumentException("TRUSTED_PROXIES berisi '{$entry}', bukan IP atau CIDR yang valid.");
        }

        if ($mask !== null && (! ctype_digit($mask) || (int) $mask < 1 || (int) $mask > ($isV4 ? 32 : 128))) {
            throw new InvalidArgumentException("TRUSTED_PROXIES berisi '{$entry}': panjang mask harus 1 sampai ".($isV4 ? 32 : 128).'.');
        }
    }
}
