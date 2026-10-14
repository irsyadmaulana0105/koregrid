<?php

use App\Domain\Audit\Contracts\AuditRecorder;
use App\Infrastructure\Audit\DatabaseAuditRecorder;
use Tests\Support\Fixtures;

it('is bound to the database implementation', function () {
    expect(app(AuditRecorder::class))->toBeInstanceOf(DatabaseAuditRecorder::class);
});

it('masks sensitive values, including nested ones', function () {
    $company = Fixtures::company();

    $log = app(AuditRecorder::class)->record('updated', $company, ['password' => 'old-secret'], [
        'name' => 'Baru',
        'token' => 'abc',
        'nested' => ['api_key' => 'k', 'ok' => 1],
    ]);

    expect($log->old_values)->toBe(['password' => '***'])
        ->and($log->new_values)->toBe(['name' => 'Baru', 'token' => '***', 'nested' => ['api_key' => '***', 'ok' => 1]]);
});

it('records the acting user, or null for system actions', function () {
    $company = Fixtures::company();
    $buyer = Fixtures::buyer($company);
    $recorder = app(AuditRecorder::class);

    $system = $recorder->record('created', $company);
    $this->actingAs($buyer);
    $actor = $recorder->record('updated', $company);

    expect($system->user_id)->toBeNull()
        ->and($actor->user_id)->toBe($buyer->id)
        ->and($actor->auditable_type)->toBe($company->getMorphClass())
        ->and($actor->auditable_id)->toBe($company->id);
});
