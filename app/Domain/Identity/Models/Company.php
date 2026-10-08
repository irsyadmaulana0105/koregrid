<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Ordering\Models\PurchaseOrder;
use App\Domain\Shared\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'tax_id',
        'billing_address',
        'pricing_tier',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'pricing_tier' => PricingTier::class,
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
