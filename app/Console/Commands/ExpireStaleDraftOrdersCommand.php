<?php

namespace App\Console\Commands;

use App\Application\Ordering\Actions\ExpireStaleDraftOrders;
use Illuminate\Console\Command;

final class ExpireStaleDraftOrdersCommand extends Command
{
    protected $signature = 'orders:expire-drafts {--limit=500 : Batas jumlah PO per eksekusi}';

    protected $description = 'Batalkan draft PO yang reservasinya kedaluwarsa dan lepaskan stoknya';

    public function handle(ExpireStaleDraftOrders $expire): int
    {
        $count = $expire(limit: (int) $this->option('limit'));

        $this->info("{$count} draft PO dibatalkan.");

        return self::SUCCESS;
    }
}
