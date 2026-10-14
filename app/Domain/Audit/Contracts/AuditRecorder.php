<?php

namespace App\Domain\Audit\Contracts;

use App\Domain\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Domain hanya mengenal kontrak ini; implementasinya ada di App\Infrastructure\Audit.
 */
interface AuditRecorder
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(string $event, Model $auditable, ?array $oldValues = null, ?array $newValues = null): AuditLog;
}
