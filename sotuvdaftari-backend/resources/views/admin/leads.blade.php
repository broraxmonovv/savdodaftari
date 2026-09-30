@extends('admin.layout')
@section('title', 'Arizalar')
@section('content')
<h1>Saytdan kelgan arizalar</h1>
<p>
@foreach(['' => 'Hammasi', 'new' => 'Yangi', 'contacted' => "Bog'lanildi", 'done' => 'Yakunlandi', 'spam' => 'Spam'] as $k => $v)
    <a class="badge {{ ($status ?? '') === $k ? 'b-green' : '' }}" href="{{ route('admin.leads', array_filter(['status' => $k, 'q' => $q])) }}">{{ $v }}@if($k !== '') ({{ $counts[$k] ?? 0 }})@endif</a>
@endforeach
</p>
<form method="get" class="row card" style="margin-bottom:14px">
    <input type="hidden" name="status" value="{{ $status }}">
    <div><label>Qidiruv (ism, telefon)</label><input name="q" value="{{ $q }}"></div>
    <button type="submit">Qidirish</button>
    <a class="btn sec" href="{{ route('admin.leads.export', array_filter(['status' => $status, 'q' => $q])) }}" style="background:#e9f8f1;color:#006b45">CSV yuklab olish</a>
</form>
<div class="scroll"><table>
<tr><th>Sana</th><th>Ism / telefon</th><th>Savdo turi</th><th>Izoh</th><th>Holat va izoh</th><th></th></tr>
@forelse($leads as $l)
<tr>
    <td>{{ $l->created_at->format('d.m.Y H:i') }}<div class="muted">{{ strtoupper($l->locale) }}</div></td>
    <td><b>{{ $l->name }}</b><div>{{ $l->phone }}</div>
        @if(isset($registered[$l->phone]))<a class="badge b-green" href="{{ route('admin.users.show', $registered[$l->phone]) }}">ilovada ro'yxatdan o'tgan</a>@endif</td>
    <td>{{ $l->business_type ?: '—' }}</td>
    <td style="max-width:260px">{{ $l->message ?: '—' }}</td>
    <td>
        <form method="post" action="{{ route('admin.leads.update', $l->id) }}" class="row">
            @csrf @method('PUT')
            <select name="status">@foreach(['new' => 'Yangi', 'contacted' => "Bog'lanildi", 'done' => 'Yakunlandi', 'spam' => 'Spam'] as $k => $v)<option value="{{ $k }}" @selected($l->status === $k)>{{ $v }}</option>@endforeach</select>
            <input name="admin_note" value="{{ $l->admin_note }}" placeholder="Izoh" style="width:150px" maxlength="255">
            <button type="submit" class="sec">Saqlash</button>
        </form>
    </td>
    <td><form method="post" action="{{ route('admin.leads.destroy', $l->id) }}" onsubmit="return confirm('O\'chirilsinmi?')">@csrf @method('DELETE')<button class="red" type="submit">✕</button></form></td>
</tr>
@empty<tr><td colspan="6" class="muted">Arizalar yo'q</td></tr>@endforelse
</table></div>
<div class="pager">{{ $leads->links() }}</div>
@endsection
