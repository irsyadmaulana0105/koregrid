<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Hak po.create ditegakkan oleh middleware route. Field lain di luar aturan (mis. harga) dibuang oleh validated().
 */
final class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product' => ['required', 'string', 'uuid'],
            'lines.*.warehouse' => ['required', 'string', 'max:50'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    /** @return list<array{product: string, warehouse: string, quantity: int}> */
    public function lines(): array
    {
        $lines = [];

        foreach ((array) $this->validated('lines') as $line) {
            if (! is_array($line)) {
                continue;
            }

            $lines[] = [
                'product' => strtolower((string) $line['product']),
                'warehouse' => (string) $line['warehouse'],
                'quantity' => (int) $line['quantity'],
            ];
        }

        return $lines;
    }
}
