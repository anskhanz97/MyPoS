<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28mm 18mm 22mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #10211d; font-family: "DejaVu Sans", sans-serif; font-size: 10px; line-height: 1.45; }
        .accent { color: #006b5d; }
        .header { border-bottom: 3px solid #006b5d; padding-bottom: 15px; margin-bottom: 20px; }
        .brand { font-size: 11px; font-weight: bold; letter-spacing: 1.4px; text-transform: uppercase; }
        h1 { margin: 7px 0 1px; font-size: 27px; line-height: 1.1; }
        .po-number { color: #42534e; font-size: 12px; }
        .status { float: right; border: 1px solid #006b5d; border-radius: 12px; padding: 4px 10px; color: #006b5d; font-weight: bold; text-transform: uppercase; }
        .grid { width: 100%; margin-bottom: 20px; }
        .grid td { width: 50%; vertical-align: top; padding-right: 18px; }
        .label { color: #687973; font-size: 8px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; }
        .value { margin: 3px 0 10px; font-size: 11px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .lines th { background: #e8f3f0; border-bottom: 1px solid #a4b8b2; padding: 8px 6px; text-align: left; font-size: 8px; letter-spacing: .5px; text-transform: uppercase; }
        .lines td { border-bottom: 1px solid #d6dfdc; padding: 9px 6px; vertical-align: top; }
        .lines .num { text-align: right; white-space: nowrap; }
        .sku { color: #687973; font-size: 8px; }
        .total { margin: 15px 0 22px auto; width: 45%; border-top: 2px solid #10211d; padding-top: 8px; }
        .total td { padding: 3px 0; }
        .total .amount { text-align: right; font-size: 15px; font-weight: bold; }
        .note { background: #f3f5f4; border-left: 3px solid #006b5d; padding: 10px 12px; margin-top: 16px; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; border-top: 1px solid #ccd7d3; padding-top: 6px; color: #687973; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <span class="status">{{ str_replace('_', ' ', $order->state->value) }}</span>
        <div class="brand accent">{{ $order->store->merchant->name }}</div>
        <h1>Purchase order</h1>
        <div class="po-number">{{ $order->number }}</div>
    </div>

    <table class="grid">
        <tr>
            <td>
                <div class="label">Supplier</div>
                <div class="value"><strong>{{ $order->supplier->name }}</strong><br>{{ $order->supplier->code }}
                    @if($order->supplier->tax_reference)<br>Tax ref: {{ $order->supplier->tax_reference }}@endif
                    @if($order->supplier->address_line_1)<br>{{ $order->supplier->address_line_1 }}@endif
                    @if($order->supplier->address_line_2)<br>{{ $order->supplier->address_line_2 }}@endif
                    @if($order->supplier->city)<br>{{ $order->supplier->city }}@endif
                </div>
            </td>
            <td>
                <div class="label">Deliver to</div>
                <div class="value"><strong>{{ $order->store->name }}</strong><br>{{ $order->store->code }}</div>
                <div class="label">Order / expected date</div>
                <div class="value">{{ $order->order_date->format('d M Y') }} / {{ $order->expected_date?->format('d M Y') ?? 'Not specified' }}</div>
                @if($order->source_reference)<div class="label">Reference</div><div class="value">{{ $order->source_reference }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead><tr><th>Item</th><th class="num">Quantity</th><th class="num">Unit cost</th><th class="num">Expected total</th></tr></thead>
        <tbody>
        @php($grandTotal = \Brick\Math\BigDecimal::zero())
        @foreach($order->lines as $line)
            @php($lineTotal = \Brick\Math\BigDecimal::of($line->ordered_quantity_uom)->multipliedBy($line->expected_unit_cost))
            @php($grandTotal = $grandTotal->plus($lineTotal))
            <tr>
                <td><strong>{{ $line->product_name_snapshot }}</strong><br><span class="sku">{{ $line->sku_snapshot ?: 'No SKU' }}@if($line->tax_reference) - Tax ref {{ $line->tax_reference }}@endif</span></td>
                <td class="num">{{ $line->ordered_quantity_uom }} {{ $line->unit_symbol_snapshot }}<br><span class="sku">{{ $line->ordered_quantity_base }} base</span></td>
                <td class="num">PKR {{ number_format((float) $line->expected_unit_cost, 2) }}</td>
                <td class="num">PKR {{ number_format((float) (string) $lineTotal, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="total"><tr><td>Expected order total</td><td class="amount">PKR {{ number_format((float) (string) $grandTotal, 2) }}</td></tr></table>
    @if($order->notes)<div class="note"><span class="label">Order notes</span><br>{{ $order->notes }}</div>@endif
    <div class="note"><strong>Planning document.</strong> Stock and supplier balances change only when a separate goods receipt is posted.</div>
    <div class="footer">Generated from {{ $order->number }} version {{ $order->version }} - {{ $order->store->name }} - Amounts are expected values in PKR.</div>
</body>
</html>
