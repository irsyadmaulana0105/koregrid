<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Email dinormalisasi sebelum divalidasi, selaras dengan indeks unik lower(email).
     * Nilai non-string dibiarkan agar ditolak oleh aturan `string` (bukan menyebabkan error 500).
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * Mencoba masuk. Hanya akun aktif yang bisa masuk, dan alasan gagalnya sengaja tidak dibedakan
     * (email tidak terdaftar, password salah, atau akun nonaktif) agar daftar akun tidak bisa ditebak.
     *
     * @throws ValidationException jika sudah terlalu banyak percobaan
     */
    public function authenticate(): bool
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => (string) $this->input('email'),
            'password' => (string) $this->input('password'),
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::LOCKOUT_SECONDS);

            return false;
        }

        RateLimiter::clear($this->throttleKey());

        return true;
    }

    /** Batas dihitung per kombinasi email dan IP: satu penyerang tidak bisa mengunci akun orang lain dari IP berbeda. */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('email')).'|'.$this->ip());
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik.",
        ]);
    }
}
