<?php

namespace App\Domain\Ordering\Exceptions;

use App\Domain\Ordering\Enums\PoStatus;
use DomainException;

final class InvalidStatusTransitionException extends DomainException
{
    public static function between(PoStatus $from, PoStatus $to): self
    {
        return new self("Status PO tidak boleh berubah dari {$from->value} ke {$to->value}.");
    }
}
