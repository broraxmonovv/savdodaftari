@extends('admin.layout')
@section('title', 'Dashboard')
@section('content')
@php($m = fn ($v) => number_format($v, 0, '.', ' ').' so\'m')
<h1>Dashboard</h1>

<div class="grid">
    <div class="card stat"><div class="l">Foydalanuvchilar</div><div class="v">{{ $stats['users_total'] }}</div><div class="muted">bugun +{{ $stats['users_today'] }} · 7 kun +{{ $stats['users_week'] }}</div></div>
    <div class="card stat"><div class="l">Faol (7 kun)</div><div class="v">{{ $stats['users_active_week'] }}</div><div class="muted">bloklangan: {{ $stats['users_blocked'] }}</div></div>
    <div class="card stat"><div class="l">Standart tarif (faol)</div><div class="v">{{ $stats['standard_active'] }}</div></div>
    <div class="card stat"><div class="l">Pro tarif (faol)</div><div class="v">{{ $stats['pro_active'] }}</div></div>
    <div class="card stat"><div class="l">Tushum (bugun)</div><div class="v">{{ $m($stats['revenue_today']) }}</div></div>
    <div class="card stat"><div class="l">Tushum (shu oy)</div><div class="v">{{ $m($stats['revenue_month']) }}</div><div class="muted">jami: {{ $m($stats['revenue_total']) }}</div></div>
    <div class="card stat"><div class="l">Yechib olish (kutilmoqda)</div><div class="v"><a href="{{ route('admin.withdrawals') }}">{{ $stats['withdrawals_pending'] }}</a></div><div class="muted">{{ $m($stats['withdrawals_pending_sum']) }}</div></div>
    <div class="card stat"><div class="l">Bonus majburiyati</div><div class="v">{{ $m($stats['bonus_liability']) }}</div><div class="muted">foydalanuvchilar balansi</div></div>
</div>

<h2>Ilovadagi umumiy ma'lumotlar</h2>
<div class="grid">
    <div class="card stat"><div class="l">Mijozlar</div><div class="v">{{ $stats['customers'] }}</div></div>
    <div class="card stat"><div class="l">Mahsulotlar</div><div class="v">{{ $stats['products'] }}</div></div>
    <div class="card stat"><div class="l">Savdolar</div><div class="v">{{ $stats['sales_count'] }}</div><div class="muted">{{ $m($stats['sales_total']) }}</div></div>
    <div class="card stat"><div class="l">Ochiq qarzlar</div><div class="v">{{ $m($stats['debts_open']) }}</div></div>
</div>

<h2>Oxirgi 14 kun</h2>
<div class="two">
    <div class="card"><div class="muted">Yangi foydalanuvchilar</div>
        @php($maxS = max(1, $chart->max('signups')))
        <div class="bars">@foreach($chart as $d)<div title="{{ $d['label'] }}: {{ $d['signups'] }}"><span>{{ $d['signups'] ?: '' }}</span><i style="height:{{ max(2, $d['signups'] / $maxS * 90) }}px"></i><span>{{ substr($d['label'], 0, 2) }}</span></div>@endforeach</div></div>
    <div class="card"><div class="muted">Tushum</div>
        @php($maxR = max(1, $chart->max('revenue')))
        <div class="bars">@foreach($chart as $d)<div title="{{ $d['label'] }}: {{ $m($d['revenue']) }}"><i class="rev" style="height:{{ max(2, $d['revenue'] / $maxR * 90) }}px"></i><span>{{ substr($d['label'], 0, 2) }}</span></div>@endforeach</div></div>
</div>

<div class="two">
    <div><h2>Yangi foydalanuvchilar</h2>
        <div class="scroll"><table><tr><th>Ism</th><th>Telefon</th><th>Sana</th></tr>
        @forelse($latestUsers as $u)<tr><td><a href="{{ route('admin.users.show', $u->id) }}">{{ $u->name ?: '—' }}</a></td><td>{{ $u->phone }}</td><td>{{ $u->created_at->format('d.m.Y H:i') }}</td></tr>
        @empty<tr><td colspan="3" class="muted">Hozircha yo'q</td></tr>@endforelse</table></div></div>
    <div><h2>Oxirgi to'lovlar</h2>
        <div class="scroll"><table><tr><th>Foydalanuvchi</th><th>Tarif</th><th>Summa</th><th>Usul</th></tr>
        @forelse($latestPayments as $p)<tr><td><a href="{{ route('admin.users.show', $p->user_id) }}">{{ $p->user?->phone }}</a></td><td>{{ $p->plan }}</td><td>{{ $m($p->amount) }}</td><td>{{ $p->provider }}</td></tr>
        @empty<tr><td colspan="4" class="muted">Hozircha yo'q</td></tr>@endforelse</table></div></div>
</div>
@endsection
