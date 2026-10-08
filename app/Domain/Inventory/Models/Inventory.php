<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    /**
     * qty_reserved sengaja tidak fillable: hanya berubah lewat Action
     * (ReserveStock, ReleaseStock, CommitStock) dengan UPDATE atomik.
     */
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'qty_on_hand',
    ];

    protected function casts(): array
    {
        return [
            'qty_on_hand' => 'integer',
            'qty_reserved' => 'integer',
        ];
    }

    /** Stok tersedia = fisik dikurangi yang terkunci (dihitung, tidak disimpan). */
    protected function available(): Attribute
    {
        return Attribute::get(fn (): int => $this->qty_on_hand - $this->qty_reserved);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
