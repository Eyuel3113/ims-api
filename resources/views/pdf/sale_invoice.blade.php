<!DOCTYPE html>
<html>
<head>
    <title>Sale Receipt</title>
    <style>
        @page { margin: 0; }
        body { 
            font-family: 'Courier', monospace; 
            margin: 0; 
            padding: 2mm 5mm; 
            font-size: 10pt;
            line-height: 1.2;
            color: #000;
        }
        .header { text-align: center; margin-bottom: 5mm; border-bottom: 1px dashed #000; padding-bottom: 2mm; }
        .header h1 { font-size: 14pt; margin: 0; }
        .info { margin-bottom: 5mm; font-size: 9pt; }
        .info p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 5mm; }
        th { text-align: left; border-bottom: 1px dashed #000; padding: 1mm 0; font-size: 9pt; }
        td { padding: 1mm 0; vertical-align: top; font-size: 9pt; }
        .item-row { border-bottom: 0.5px dotted #ccc; }
        .total-section { border-top: 1px dashed #000; padding-top: 2mm; }
        .total-row { font-weight: bold; font-size: 11pt; display: flex; justify-content: space-between; margin-top: 1mm; }
        .footer { text-align: center; margin-top: 5mm; font-size: 8pt; border-top: 1px dashed #000; padding-top: 2mm; }
        .qr-code { text-align: center; margin-top: 5mm; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RECEIPT</h1>
        <p><strong>{{ config('app.name', 'IMS') }}</strong></p>
    </div>

    <div class="info">
        <p><strong>Inv:</strong> #{{ $sale->invoice_number }}</p>
        <p><strong>Date:</strong> {{ date('Y-m-d H:i', strtotime($sale->sale_date)) }}</p>
        <p><strong>By:</strong> {{ Auth::user()->name ?? 'Admin' }}</p>
        <p><strong>Method:</strong> {{ ucfirst($sale->payment_method) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="50%">Item</th>
                <th width="15%">Qty</th>
                <th width="35%" align="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
            <tr class="item-row">
                <td>{{ substr($item->product->name, 0, 20) }}</td>
                <td>{{ $item->quantity }}</td>
                <td align="right">{{ number_format($item->total_price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row" style="width: 100%;">
            <span>GRAND TOTAL:</span>
            <span style="float: right;">{{ number_format($sale->total_amount, 2) }} ETB</span>
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>Please keep this receipt for your records.</p>
    </div>
</body>
</html>
