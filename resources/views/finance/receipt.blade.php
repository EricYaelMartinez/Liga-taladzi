<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $payment->receipt_number }}</title>
    <style>
        @page { margin: 18mm; }
        body { color: #172033; font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
        .header { border-radius: 10px; background: {{ $league->primary_color }}; color: white; padding: 20px; }
        .header h1 { margin: 0 0 4px; font-size: 22px; }
        .header p { margin: 0; }
        .meta { width: 100%; margin: 20px 0; border-collapse: collapse; }
        .meta td { padding: 7px 0; border-bottom: 1px solid #dbe2ea; }
        table.items { width: 100%; border-collapse: collapse; }
        .items th { background: #eef3f7; text-align: left; padding: 9px; }
        .items td { padding: 9px; border-bottom: 1px solid #dbe2ea; }
        .right { text-align: right; }
        .total { margin-top: 18px; text-align: right; font-size: 18px; font-weight: bold; color: {{ $league->primary_color }}; }
        .void { margin: 18px 0; border: 2px solid #b91c1c; color: #b91c1c; padding: 10px; text-align: center; font-weight: bold; }
        .footer { margin-top: 40px; color: #526174; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $league->name }}</h1>
        <p>Recibo de pago {{ $payment->receipt_number }}</p>
    </div>
    @if($payment->voided_at)<div class="void">RECIBO ANULADO — {{ $payment->void_reason }}</div>@endif
    <table class="meta">
        <tr><td><strong>Equipo</strong></td><td>{{ $payment->team->name }}</td></tr>
        <tr><td><strong>Fecha de pago</strong></td><td>{{ $payment->paid_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td><strong>Forma de pago</strong></td><td>Efectivo</td></tr>
        <tr><td><strong>Recibió</strong></td><td>{{ $payment->receiver?->name ?? 'Administración de la liga' }}</td></tr>
    </table>
    <table class="items">
        <thead><tr><th>Concepto aplicado</th><th class="right">Importe</th></tr></thead>
        <tbody>
            @foreach($payment->allocations as $allocation)
                <tr><td>{{ $allocation->charge->description }}</td><td class="right">$ {{ number_format((float) $allocation->amount, 2) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <div class="total">Total: $ {{ number_format((float) $payment->amount, 2) }} {{ $league->setting?->currency ?? 'MXN' }}</div>
    @if($payment->notes)<p><strong>Observaciones:</strong> {{ $payment->notes }}</p>@endif
    <div class="footer">Documento generado por el sistema de {{ $league->name }}. El historial financiero no se elimina.</div>
</body>
</html>
