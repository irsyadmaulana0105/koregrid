<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\Contracts\AuditRecorder;
use App\Domain\Identity\Models\User;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AuthenticatedSessionController
{
    private const FAILED_MESSAGE = 'Email atau password salah.';

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request, AuditRecorder $audit): RedirectResponse
    {
        if (! $request->authenticate()) {
            $this->recordFailedLogin($request, $audit);

            throw ValidationException::withMessages(['email' => self::FAILED_MESSAGE]);
        }

        // Id sesi diganti setelah login: mencegah session fixation.
        $request->session()->regenerate();

        $user = Auth::user();
        if ($user instanceof User) {
            $user->forceFill(['last_login_at' => now()])->save();
            $audit->record('login', $user, null, null, $user->id);
        }

        return redirect()->intended('/dashboard');
    }

    public function destroy(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $user = Auth::user();
        if ($user instanceof User) {
            $audit->record('logout', $user, null, null, $user->id);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /** Percobaan gagal terhadap akun yang ada dicatat (tanpa password); email yang tidak dikenal tidak punya objek audit. */
    private function recordFailedLogin(LoginRequest $request, AuditRecorder $audit): void
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if ($user !== null) {
            $audit->record('login_failed', $user);
        }
    }
}
