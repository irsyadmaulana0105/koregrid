<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Shared\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'unit',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(ProductTier::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /** Harga dan MOQ untuk tier tertentu; memakai relasi yang sudah di-load bila ada. */
    public function tierFor(PricingTier $tier): ?ProductTier
    {
        if ($this->relationLoaded('tiers')) {
            return $this->tiers->first(fn (ProductTier $t) => $t->tier === $tier);
        }

        return $this->tiers()->where('tier', $tier->value)->first();
    }
}
