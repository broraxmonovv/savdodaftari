@extends('admin.layout')
@section('title', 'Foydalanuvchilar')
@section('content')
<h1>Foydalanuvchilar</h1>
<form method="get" class="row card" style="margin-bottom:14px">
    <div><label>Qidiruv (ism, telefon, do'kon)</label><input name="q" value="{{ $q }}" placeholder="+99890..."></div>
    <div><label>Tarif</label><select name="plan"><option value="">Hammasi</option>@foreach(['free'=>'Bepul','standard'=>'Standart','pro'=>'Pro'] as $k=>$v)<option value="{{ $k }}" @selected($plan===$k)>{{ $v }}</option>@endforeach</select></div>
    <div><label>Holat</label><select name="status"><option value="">Hammasi</option><option value="active" @selected($status==='active')>Faol</option><option value="blocked" @selected($status==='blocked')>Bloklangan</option></select></div>
    <button type="submit">Qidirish</button>
</form>
<div class="scroll"><table>
<tr><th>ID</th><th>Ism</th><th>Telefon</th><th>Do'kon</th><th>Tarif</th><th>Bonus</th><th>Ro'yxatdan o'tgan</th><th>Oxirgi kirish</th><th>Holat</th></tr>
@forelse($users as $u)
<tr>
    <td>{{ $u->id }}</td>
    <td><a href="{{ route('admin.users.show', $u->id) }}">{{ $u->name ?: '—' }}</a></td>
    <td>{{ $u->phone }}</td><td>{{ $u->shop_name ?: '—' }}</td>
    <td>@php($p = $u->currentPlan())<span class="badge {{ $p === 'pro' ? 'b-blue' : ($p === 'standard' ? 'b-green' : '') }}">{{ $p }}</span></td>
    <td>{{ number_format((float) $u->bonus_balance, 0, '.', ' ') }}</td>
    <td>{{ $u->created_at->format('d.m.Y') }}</td>
    <td>{{ $u->last_login_at?->format('d.m.Y H:i') ?: '—' }}</td>
    <td>@if($u->isBlocked())<span class="badge b-red">bloklangan</span>@else<span class="badge b-green">faol</span>@endif</td>
</tr>
@empty<tr><td colspan="9" class="muted">Foydalanuvchi topilmadi</td></tr>@endforelse
</table></div>
<div class="pager">{{ $users->links() }}</div>
@endsection
