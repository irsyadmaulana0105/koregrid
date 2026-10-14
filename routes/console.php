<?php

use Illuminate\Support\Facades\Schedule;

// Melepas stok dari draft PO yang reservasinya kedaluwarsa.
Schedule::command('orders:expire-drafts')->everyFiveMinutes()->withoutOverlapping();
