<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1a1a1a; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #006b45; }
        h2 { font-size: 13px; margin: 18px 0 6px; }
        .sub { color: #7a828a; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e9f8f1; color: #006b45; text-align: left; padding: 5px 6px; border-bottom: 1px solid #c9e7da; }
        td { padding: 4px 6px; border-bottom: 1px solid #e6eaed; }
        .empty { color: #7a828a; padding: 6px 0; }
        .footer { margin-top: 18px; color: #7a828a; font-size: 8px; }
    </style>
</head>
<body>
<h1>Savdo Up — {{ $document['title'] }}</h1>
<div class="sub">{{ $document['subtitle'] }}</div>

@foreach($document['sections'] as $section)
    @if(count($document['sections']) > 1)<h2>{{ $section['title'] }}</h2>@endif
    @if($section['rows'] === [])
        <div class="empty">{{ __('export.empty') }}</div>
    @else
        <table>
            <thead><tr>@foreach($section['headers'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach($section['rows'] as $row)
                <tr>@foreach($row as $cell)<td>{{ is_float($cell) || is_int($cell) ? number_format($cell, is_int($cell) ? 0 : (floor($cell) == $cell ? 0 : 2), '.', ' ') : $cell }}</td>@endforeach</tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endforeach

<div class="footer">Savdo Up · {{ now()->format('d.m.Y H:i') }}</div>
</body>
</html>
