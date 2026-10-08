<?php

namespace App\Domain\Ordering\Enums;

enum PoStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Packing = 'packing';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * State machine PO (lihat docs/DATA-MODEL.md).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingApproval, self::Cancelled],
            self::PendingApproval => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Packing],
            self::Packing => [self::Shipped],
            self::Shipped => [self::Completed],
            self::Completed, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** Pada status ini stok PO masih terkunci (qty_reserved). Dilepas saat cancel/reject, di-commit saat shipped. */
    public function holdsReservation(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval, self::Approved, self::Packing], true);
    }
}
