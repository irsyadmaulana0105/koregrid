<?php

namespace App\Http\Requests;

final class ShipOrderRequest extends OrderActionRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['tracking_number' => ['required', 'string', 'max:100']];
    }

    public function trackingNumber(): string
    {
        return trim(strip_tags((string) $this->validated('tracking_number')));
    }
}
