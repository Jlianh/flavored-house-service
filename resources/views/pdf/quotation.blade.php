<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; }
        .header { background: #bc1a18; color: white; padding: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f5c400; }
        td, th { padding: 8px; border: 1px solid #ddd; }
    </style>
</head>
<body>

<div class="header">
    <h2>COTIZACIÓN {{ $quotationNumber }}</h2>
</div>

<p><strong>Cliente:</strong> {{ $clientName }}</p>
<p><strong>Email:</strong> {{ $clientEmail }}</p>

<table>
    <thead>
        <tr>
            <th>Producto</th>
            <th>Gramaje</th>
            <th>Cantidad</th>
        </tr>
    </thead>
    <tbody>
        @foreach($quotationItems as $item)
        <tr>
            <td>{{ $item['name'] }}</td>
            <td>{{ $item['grammage'] }}</td>
            <td>{{ $item['quantity'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>