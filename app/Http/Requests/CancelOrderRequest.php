<?php

namespace App\Http\Requests;

final class CancelOrderRequest extends OrderActionRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:1000']];
    }

    public function reason(): ?string
    {
        $reason = trim(strip_tags((string) $this->validated('reason')));

        return $reason === '' ? null : $reason;
    }
}
