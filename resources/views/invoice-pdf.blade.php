<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice['invoice_number'] }}</title>
    <style>
        @page { margin: 24mm 16mm; }
        * { box-sizing: border-box; font-family: DejaVu Sans, Helvetica, Arial, sans-serif; }
        body { font-size: 11px; color: #0f172a; }
        .header { width: 100%; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
        .brand { font-size: 20px; font-weight: bold; }
        .muted { color: #64748b; font-size: 9px; }
        .title { font-size: 16px; font-weight: bold; text-transform: uppercase; text-align: right; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { vertical-align: top; padding: 2px 0; font-size: 10px; }
        .items th { border-bottom: 1px solid #cbd5e1; text-align: left; font-size: 9px; text-transform: uppercase; color: #64748b; padding: 6px 4px; }
        .items td { border-bottom: 1px solid #e2e8f0; padding: 6px 4px; font-size: 10px; }
        .right { text-align: right; }
        .totals { width: 260px; margin-left: auto; margin-top: 12px; }
        .totals td { padding: 3px 0; font-size: 10px; }
        .grand td { border-top: 1px solid #0f172a; font-weight: bold; font-size: 12px; padding-top: 6px; }
        .footer { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 8px; font-size: 8px; color: #94a3b8; }
    </style>
</head>
<body>
    @php
        $rp = fn (mixed $v): string => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $date = fn (?string $iso): string => $iso === null ? '-' : \Carbon\Carbon::parse($iso)->locale('id')->translatedFormat('d F Y');
    @endphp

    <table class="header">
        <tr>
            <td>
                <div class="brand">KoreGrid</div>
                <div class="muted">Enterprise B2B Supply Chain &amp; Multi-Warehouse Portal</div>
            </td>
            <td class="right">
                <div class="title">Invoice</div>
                <div class="mono">{{ $invoice['invoice_number'] }}</div>
                <div class="muted">Terbit {{ $date($invoice['invoice_issued_at']) }}</div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td style="width: 55%;">
                <div class="muted">DITAGIHKAN KEPADA</div>
                <strong>{{ $invoice['company'] }}</strong><br>
                @if ($invoice['billing_address'])
                    {!! nl2br(e($invoice['billing_address'])) !!}
                @endif
            </td>
            <td class="right">
                <div class="muted">REFERENSI</div>
                PO {{ $invoice['po_number'] }}<br>
                Tier harga {{ $invoice['pricing_tier'] }}
                @if ($invoice['tracking_number'])
                    <br>Resi {{ $invoice['tracking_number'] }}
                @endif
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top: 16px;">
        <thead>
            <tr>
                <th>Produk</th>
                <th>Gudang</th>
                <th class="right">Jumlah</th>
                <th class="right">Harga</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice['items'] as $item)
                <tr>
                    <td>
                        {{ $item['name'] }}<br>
                        <span class="muted">{{ $item['sku'] }}</span>
                    </td>
                    <td>{{ $item['warehouse_code'] }}</td>
                    <td class="right">{{ $item['quantity'] }} {{ $item['unit'] }}</td>
                    <td class="right">{{ $rp($item['unit_price']) }}</td>
                    <td class="right">{{ $rp($item['line_total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="right">{{ $rp($invoice['subtotal']) }}</td>
        </tr>
        <tr>
            <td>PPN {{ $invoice['tax_percent'] }}%</td>
            <td class="right">{{ $rp($invoice['tax_amount']) }}</td>
        </tr>
        <tr class="grand">
            <td>Total</td>
            <td class="right">{{ $rp($invoice['total_amount']) }}</td>
        </tr>
    </table>

    <div class="footer">
        Dokumen ini dibuat secara elektronik oleh portal KoreGrid dan sah tanpa tanda tangan basah.
    </div>
</body>
</html>
