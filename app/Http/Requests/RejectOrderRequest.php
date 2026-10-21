<?php

namespace App\Http\Requests;

final class RejectOrderRequest extends OrderActionRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }

    public function reason(): string
    {
        return trim(strip_tags((string) $this->validated('reason')));
    }
}
