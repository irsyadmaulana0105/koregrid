<?php

namespace App\Infrastructure\Audit;

use App\Domain\Audit\Contracts\AuditRecorder;
use App\Domain\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Menulis audit log ke database beserta konteks request (aktor, IP, user agent, request id).
 * Di console (job, scheduler) konteks request kosong dan aktor null = aksi sistem.
 */
final class DatabaseAuditRecorder implements AuditRecorder
{
    /** Nama field yang nilainya tidak boleh masuk log. */
    private const SENSITIVE_KEYS = ['password', 'remember_token', 'token', 'secret', 'api_key'];

    public function record(string $event, Model $auditable, ?array $oldValues = null, ?array $newValues = null, ?int $actorId = null): AuditLog
    {
        $request = app()->runningInConsole() ? null : request();
        $userAgent = $request === null ? '' : mb_substr((string) $request->userAgent(), 0, 255);
        $requestId = $request?->header('X-Request-Id');

        return AuditLog::create([
            'user_id' => $actorId ?? Auth::id(),
            'event' => $event,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $this->mask($oldValues),
            'new_values' => $this->mask($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => $userAgent === '' ? null : $userAgent,
            'request_id' => is_string($requestId) && Str::isUuid($requestId) ? $requestId : null,
        ]);
    }

    /**
     * @param  array<mixed>|null  $values
     * @return array<mixed>|null
     */
    private function mask(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $masked = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $masked[$key] = '***';
            } else {
                $masked[$key] = is_array($value) ? $this->mask($value) : $value;
            }
        }

        return $masked;
    }
}
