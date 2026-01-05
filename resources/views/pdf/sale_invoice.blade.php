<!DOCTYPE html>
<html>
<head>
    <title>Sale Invoice</title>
    <style>
        body { font-family: DejaVu Sans; }
        .header { text-align: center; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .total { font-weight: bold; font-size: 1.2em; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sale Invoice</h1>
        <p>Invoice: {{ $sale->invoice_number }}</p>
        <p>Date: {{ $sale->sale_date }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Warehouse</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
            <tr>
                <td>{{ $item->product->name }}</td>
                <td>{{ $item->warehouse->name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ number_format($item->unit_price, 2) }}</td>
                <td>{{ number_format($item->total_price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="4">Grand Total</td>
                <td>{{ number_format($sale->total_amount, 2) }} ETB</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
