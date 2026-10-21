<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memberi setiap request sebuah id (UUID) untuk menghubungkan log aplikasi, audit log, dan respons.
 * Id dari klien hanya dipakai jika berbentuk UUID yang valid; nilai lain diganti, sehingga tidak bisa menyisipkan teks.
 */
final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get('X-Request-Id');
        $id = is_string($incoming) && Str::isUuid($incoming) ? strtolower($incoming) : (string) Str::uuid();

        $request->headers->set('X-Request-Id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
