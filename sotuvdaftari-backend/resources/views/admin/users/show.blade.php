@extends('admin.layout')
@section('title', $user->name ?: $user->phone)
@section('content')
@php($m = fn ($v) => number_format((float) $v, 0, '.', ' ').' so\'m')
<p><a href="{{ route('admin.users') }}">← Foydalanuvchilar</a></p>
<h1>{{ $user->name ?: '—' }} <span class="muted" style="font-size:14px">{{ $user->phone }}</span>
    @if($user->isBlocked())<span class="badge b-red">bloklangan</span>@endif</h1>

<div class="two">
    <div class="card">
        <h2 style="margin-top:0">Ma'lumotlar</h2>
        <table>
            <tr><td class="muted">ID</td><td>{{ $user->id }}</td></tr>
            <tr><td class="muted">Do'kon</td><td>{{ $user->shop_name ?: '—' }} @if($user->business_type)<span class="muted">({{ $user->business_type }})</span>@endif</td></tr>
            <tr><td class="muted">Til</td><td>{{ $user->locale }}</td></tr>
            <tr><td class="muted">Ro'yxatdan o'tgan</td><td>{{ $user->created_at->format('d.m.Y H:i') }}</td></tr>
            <tr><td class="muted">Oxirgi kirish</td><td>{{ $user->last_login_at?->format('d.m.Y H:i') ?: '—' }}</td></tr>
            <tr><td class="muted">PIN</td><td>{{ $user->hasPin() ? 'o\'rnatilgan' : 'yo\'q' }}</td></tr>
            <tr><td class="muted">Qurilmalar (sessiyalar)</td><td>{{ $tokens->count() }} @foreach($tokens as $t)<div class="muted">{{ $t->name }} · {{ $t->last_used_at?->format('d.m.Y H:i') ?: $t->created_at->format('d.m.Y H:i') }}</div>@endforeach</td></tr>
            <tr><td class="muted">Taklif qilgan</td><td>@if($referrer)<a href="{{ route('admin.users.show', $referrer->id) }}">{{ $referrer->name ?: $referrer->phone }}</a>@else—@endif</td></tr>
            <tr><td class="muted">Referal kodi</td><td>{{ $user->referral_code ?: '—' }}</td></tr>
            <tr><td class="muted">Bonus balansi</td><td><b>{{ $m($user->bonus_balance) }}</b></td></tr>
            @if($user->isBlocked())<tr><td class="muted">Bloklash sababi</td><td>{{ $user->block_reason ?: '—' }} <span class="muted">({{ $user->blocked_at->format('d.m.Y H:i') }})</span></td></tr>@endif
        </table>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Tarif va amallar</h2>
        <p>Joriy tarif: <span class="badge {{ $plan === 'pro' ? 'b-blue' : ($plan === 'standard' ? 'b-green' : '') }}">{{ $plan }}</span>
            @if($subscription)<span class="muted">— {{ $subscription->expires_at->format('d.m.Y H:i') }} gacha</span>@endif</p>

        <form method="post" action="{{ route('admin.users.grant', $user->id) }}" class="row">
            @csrf
            <div><label>Tarif berish</label><select name="plan"><option value="standard">Standart</option><option value="pro">Pro</option></select></div>
            <div><label>Kun</label><input type="number" name="days" value="30" min="1" max="3650" style="width:90px"></div>
            <button type="submit" class="sec">Berish / uzaytirish</button>
        </form>

        <h2>Bloklash</h2>
        @if($user->isBlocked())
            <form method="post" action="{{ route('admin.users.unblock', $user->id) }}">@csrf<button type="submit">Blokdan chiqarish</button></form>
        @else
            <form method="post" action="{{ route('admin.users.block', $user->id) }}" class="row" onsubmit="return confirm('Foydalanuvchi bloklansinmi? Barcha qurilmalardan chiqariladi.')">
                @csrf
                <div style="flex:1"><label>Sabab (ixtiyoriy)</label><input name="reason" style="width:100%" maxlength="255"></div>
                <button type="submit" class="red">Bloklash</button>
            </form>
        @endif
    </div>
</div>

<h2>Ilovadagi ma'lumotlari</h2>
<div class="grid">
    <div class="card stat"><div class="l">Mijozlar</div><div class="v">{{ $counts['customers'] }}</div></div>
    <div class="card stat"><div class="l">Ochiq qarz</div><div class="v">{{ $m($counts['debts_open']) }}</div></div>
    <div class="card stat"><div class="l">Mahsulotlar</div><div class="v">{{ $counts['products'] }}</div></div>
    <div class="card stat"><div class="l">Savdolar</div><div class="v">{{ $counts['sales'] }}</div><div class="muted">{{ $m($counts['sales_total']) }}</div></div>
    <div class="card stat"><div class="l">Xarajatlar</div><div class="v">{{ $m($counts['expenses_total']) }}</div></div>
</div>

<div class="two">
    <div><h2>Mijozlar (oxirgi 10)</h2><div class="scroll"><table><tr><th>Ism</th><th>Telefon</th><th>Balans</th></tr>
        @forelse($customers as $c)<tr><td>{{ $c->name }}</td><td>{{ $c->phone }}</td><td>{{ $m($c->balance) }}</td></tr>@empty<tr><td colspan="3" class="muted">Yo'q</td></tr>@endforelse</table></div></div>
    <div><h2>Qarzlar (oxirgi 10)</h2><div class="scroll"><table><tr><th>Mijoz</th><th>Summa</th><th>To'langan</th><th>Muddat</th></tr>
        @forelse($debts as $d)<tr><td>{{ $d->customer?->name }}</td><td>{{ $m($d->amount) }}</td><td>{{ $m($d->paid_amount) }}</td><td>{{ $d->due_date?->format('d.m.Y') ?: '—' }}</td></tr>@empty<tr><td colspan="4" class="muted">Yo'q</td></tr>@endforelse</table></div></div>
    <div><h2>Mahsulotlar (oxirgi 10)</h2><div class="scroll"><table><tr><th>Nomi</th><th>Qoldiq</th><th>Narx</th></tr>
        @forelse($products as $p)<tr><td>{{ $p->name }}</td><td>{{ $p->stock + 0 }} {{ $p->unit }}</td><td>{{ $m($p->sell_price) }}</td></tr>@empty<tr><td colspan="3" class="muted">Yo'q</td></tr>@endforelse</table></div></div>
    <div><h2>Savdolar (oxirgi 10)</h2><div class="scroll"><table><tr><th>Sana</th><th>Jami</th><th>Usul</th><th>Foyda</th></tr>
        @forelse($sales as $s)<tr><td>{{ $s->sold_at?->format('d.m.Y H:i') }}</td><td>{{ $m($s->total) }}</td><td>{{ $s->payment_method }}</td><td>{{ $m($s->profit) }}</td></tr>@empty<tr><td colspan="4" class="muted">Yo'q</td></tr>@endforelse</table></div></div>
    <div><h2>Xarajatlar (oxirgi 10)</h2><div class="scroll"><table><tr><th>Sana</th><th>Kategoriya</th><th>Summa</th></tr>
        @forelse($expenses as $e)<tr><td>{{ $e->spent_at?->format('d.m.Y') }}</td><td>{{ $e->category }}</td><td>{{ $m($e->amount) }}</td></tr>@empty<tr><td colspan="3" class="muted">Yo'q</td></tr>@endforelse</table></div></div>
    <div><h2>To'lovlar</h2><div class="scroll"><table><tr><th>Sana</th><th>Tarif</th><th>Summa</th><th>Usul</th><th>Holat</th></tr>
        @forelse($payments as $p)<tr><td>{{ $p->created_at->format('d.m.Y H:i') }}</td><td>{{ $p->plan }}</td><td>{{ $m($p->amount) }}</td><td>{{ $p->provider }}</td><td><span class="badge {{ $p->status === 'paid' ? 'b-green' : ($p->status === 'pending' ? 'b-amb' : 'b-red') }}">{{ $p->status }}</span></td></tr>@empty<tr><td colspan="5" class="muted">Yo'q</td></tr>@endforelse</table></div></div>
    <div><h2>Obunalar</h2><div class="scroll"><table><tr><th>Tarif</th><th>Boshlangan</th><th>Tugaydi</th><th>Holat</th></tr>
        @forelse($subscriptions as $s)<tr><td>{{ $s->plan }}</td><td>{{ $s->started_at?->format('d.m.Y') }}</td><td>{{ $s->expires_at?->format('d.m.Y') }}</td><td><span class="badge {{ $s->isActive() ? 'b-green' : '' }}">{{ $s->isActive() ? 'faol' : $s->status }}</span></td></tr>@empty<tr><td colspan="4" class="muted">Yo'q</td></tr>@endforelse</table></div></div>
    <div><h2>Bonuslar</h2><div class="scroll"><table><tr><th>Sana</th><th>Tur</th><th>Summa</th></tr>
        @forelse($bonuses as $b)<tr><td>{{ $b->created_at->format('d.m.Y H:i') }}</td><td>{{ $b->type }}</td><td>{{ $m($b->amount) }}</td></tr>@empty<tr><td colspan="3" class="muted">Yo'q</td></tr>@endforelse</table></div></div>
    <div><h2>Yechib olish so'rovlari</h2><div class="scroll"><table><tr><th>Sana</th><th>Summa</th><th>Karta</th><th>Holat</th></tr>
        @forelse($withdrawals as $w)<tr><td>{{ $w->created_at->format('d.m.Y H:i') }}</td><td>{{ $m($w->amount) }}</td><td>{{ $w->maskedCard() }}</td><td>{{ $w->status }}</td></tr>@empty<tr><td colspan="4" class="muted">Yo'q</td></tr>@endforelse</table></div></div>
    <div><h2>Taklif qilganlari</h2><div class="scroll"><table><tr><th>Foydalanuvchi</th><th>Sana</th></tr>
        @forelse($referrals as $r)<tr><td><a href="{{ route('admin.users.show', $r->id) }}">{{ $r->name ?: $r->phone }}</a></td><td>{{ $r->created_at->format('d.m.Y') }}</td></tr>@empty<tr><td colspan="2" class="muted">Yo'q</td></tr>@endforelse</table></div></div>
</div>
@endsection
