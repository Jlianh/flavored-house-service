<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; }
        .title { font-size: 18px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #bc1a18; color: white; }
        td, th { padding: 6px; border: 1px solid #ddd; font-size: 12px; }
    </style>
</head>
<body>

<div class="title">
    REMISIÓN {{ $remisionNumber }}
</div>

<p><strong>Cliente:</strong> {{ $clientName }}</p>
<p><strong>NIT:</strong> {{ $clientId }}</p>

<table>
    <thead>
        <tr>
            <th>Producto</th>
            <th>Cant</th>
            <th>Precio</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($billItems as $item)
        <tr>
            <td>{{ $item['name'] }}</td>
            <td>{{ $item['quantity'] }}</td>
            <td>{{ $item['unitaryPrice'] }}</td>
            <td>{{ $item['subtotal'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<br>

<p><strong>Subtotal:</strong> {{ $subtotal }}</p>
<p><strong>IVA:</strong> {{ $totalIva }}</p>
<p><strong>Total:</strong> {{ $totalOperation }}</p>

</body>
</html>