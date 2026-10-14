<?php

return [
    // PPN, sebagai pecahan desimal (string agar bebas error float).
    'tax_rate' => env('KOREGRID_TAX_RATE', '0.11'),

    // Lama stok terkunci untuk draft PO sebelum dilepas otomatis.
    'draft_reservation_ttl_hours' => (int) env('KOREGRID_DRAFT_TTL_HOURS', 24),
];
