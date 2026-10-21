<?php

namespace App\Http\Requests;

use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Dasar semua aksi pada sebuah PO. PO yang tidak boleh dilihat user dijawab 404 (bukan 403)
 * dan diperiksa sebelum validasi, sehingga keberadaan PO milik perusahaan lain tidak bocor.
 * Hak untuk melakukan aksinya sendiri ditegakkan oleh Policy di dalam use case.
 */
class OrderActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $order = $this->route('order');

        return $user instanceof User && $order instanceof PurchaseOrder && $user->can('view', $order);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [];
    }

    protected function failedAuthorization(): never
    {
        throw new NotFoundHttpException;
    }
}
