<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ведомость поездки</title>
    <style>
        body { font-family: Arial, sans-serif; color:#111; margin:24px; }
        h1 { margin:0 0 8px; font-size:24px; }
        .meta { margin-bottom:18px; color:#444; line-height:1.5; }
        .summary { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:18px; }
        .summary div { border:1px solid #bbb; border-radius:8px; padding:8px 12px; }
        table { width:100%; border-collapse:collapse; font-size:12px; }
        th, td { border:1px solid #888; padding:7px; vertical-align:top; }
        th { background:#eee; }
        .print-actions { margin-bottom:18px; }
        @media print { .print-actions { display:none; } body { margin:0; } @page { size:landscape; margin:10mm; } }
    </style>
</head>
<body>
<div class="print-actions"><button onclick="window.print()">Печать / PDF</button></div>
<h1>Ведомость участников паломнической поездки</h1>
<div class="meta">
    <strong>{{ optional($trip->pilgrimageRoute)->title ?: $trip->title }}</strong><br>
    Дата и время: {{ $trip->starts_at->format('d.m.Y H:i') }}<br>
    Место встречи: {{ $trip->meeting_point ?: 'не указано' }}
</div>
<div class="summary">
    <div>Вместимость: <strong>{{ $summary['capacity'] ?? 'без ограничения' }}</strong></div>
    <div>Занято мест: <strong>{{ $summary['booked'] }}</strong></div>
    <div>Едут: <strong>{{ $summary['going'] }}</strong></div>
    <div>Не определились: <strong>{{ $summary['pending'] }}</strong></div>
    <div>Прибыли: <strong>{{ $summary['attended'] }}</strong></div>
</div>
<table>
    <thead>
    <tr><th>№</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Решение</th><th>Явка</th><th>Код заявки</th><th>Оплата</th><th>Примечание</th><th>Подпись</th></tr>
    </thead>
    <tbody>
    @foreach($participants as $participant)
        @php($booking = $participant->booking)
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ $participant->full_name }}</td>
            <td>{{ $participant->phone ?: $booking->phone }}</td>
            <td>{{ $participant->email ?: $booking->email }}</td>
            <td>{{ $decisionStatuses[$participant->decision_status] ?? $participant->decision_status }}</td>
            <td>{{ $attendanceStatuses[$participant->attendance_status] ?? $participant->attendance_status }}</td>
            <td>{{ $booking->ticket_code }}</td>
            <td>{{ number_format((float)$participant->paid_amount, 2, ',', ' ') }} ₽</td>
            <td>{{ $participant->notes }}</td>
            <td style="min-width:90px"></td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
