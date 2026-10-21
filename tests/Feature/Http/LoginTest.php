<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);

    // Bersihkan hitungan percobaan untuk kunci yang dipakai test ini (aman walau cache bukan array).
    RateLimiter::clear(Str::transliterate('budi@koregrid.test|127.0.0.1'));
});

/** @param array<string, mixed> $attributes */
function makeLoginUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'email' => 'budi@koregrid.test',
        'password' => Hash::make('rahasia-123'),
    ], $attributes));
}

function loginAudits(string $event, User $user): int
{
    return AuditLog::query()->where('event', $event)->where('auditable_id', $user->id)->count();
}

it('logs in an active user, stamps last_login_at and writes an audit record', function () {
    $user = makeLoginUser();

    $this->post('/login', ['email' => 'budi@koregrid.test', 'password' => 'rahasia-123'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(loginAudits('login', $user))->toBe(1);
});

it('accepts the email in any letter case', function () {
    $user = makeLoginUser();

    $this->post('/login', ['email' => 'BUDI@KoreGrid.TEST', 'password' => 'rahasia-123'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password and audits the failed attempt', function () {
    $user = makeLoginUser();

    $this->from('/login')
        ->post('/login', ['email' => 'budi@koregrid.test', 'password' => 'salah'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

    $this->assertGuest();
    expect(loginAudits('login_failed', $user))->toBe(1)
        ->and(loginAudits('login', $user))->toBe(0);
});

it('gives the same error for an inactive account and an unknown email', function () {
    makeLoginUser(['is_active' => false]);

    $messages = [];
    foreach (['budi@koregrid.test', 'tidak.ada@koregrid.test'] as $email) {
        $this->post('/login', ['email' => $email, 'password' => 'rahasia-123']);
        $this->assertGuest();
        $messages[] = session('errors')->first('email');
    }

    expect($messages[0])->toBe('Email atau password salah.')
        ->and($messages[1])->toBe($messages[0]);
});

it('does not crash when the email is sent as an array', function () {
    $this->post('/login', ['email' => ['x'], 'password' => 'rahasia-123'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('locks out after five failed attempts even when the next password is correct', function () {
    makeLoginUser();

    foreach (range(1, 5) as $ignored) {
        $this->post('/login', ['email' => 'budi@koregrid.test', 'password' => 'salah']);
    }

    $this->post('/login', ['email' => 'budi@koregrid.test', 'password' => 'rahasia-123'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(session('errors')->first('email'))->toContain('Terlalu banyak percobaan');
});

it('logs out, invalidates the session and writes an audit record', function () {
    $user = makeLoginUser();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
    expect(loginAudits('logout', $user))->toBe(1);
});

it('redirects a logged-in user away from the login page', function () {
    $this->actingAs(makeLoginUser())->get('/login')->assertRedirect('/dashboard');
});
