<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $payment->payment_id }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
    </style>
</head>
<body>
    <h1>Invoice</h1>
    <table>
        <tr><th>Payment ID</th><td>{{ $payment->payment_id }}</td></tr>
        <tr><th>Order</th><td>{{ $payment->order_name }}</td></tr>
        <tr><th>Amount</th><td>{{ $payment->amount }}</td></tr>
        <tr><th>Paid at</th><td>{{ $payment->paid_at?->toIso8601String() ?? '' }}</td></tr>
        <tr><th>Status</th><td>{{ $payment->status }}</td></tr>
    </table>
</body>
</html>
