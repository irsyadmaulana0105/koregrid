<?php

namespace App\Domain\Identity\Enums;

enum PricingTier: string
{
    case Gold = 'gold';
    case Silver = 'silver';
    case Bronze = 'bronze';
}
