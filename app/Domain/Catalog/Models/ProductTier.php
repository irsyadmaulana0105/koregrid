<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Identity\Enums\PricingTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTier extends Model
{
    protected $fillable = [
        'tier',
        'unit_price',
        'moq',
    ];

    protected function casts(): array
    {
        return [
            'tier' => PricingTier::class,
            'unit_price' => 'decimal:2',
            'moq' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
