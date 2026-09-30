@extends('admin.layout')
@section('title', 'Bildirishnomalar')
@section('content')
<h1>Bildirishnomalar</h1>
<form method="post" action="{{ route('admin.announcements.store') }}" class="card" style="max-width:640px">
    @csrf
    <label>Sarlavha</label><input name="title" value="{{ old('title') }}" maxlength="150" style="width:100%" required>
    <label>Matn</label><textarea name="body" maxlength="2000" required>{{ old('body') }}</textarea>
    <div class="row">
        <div><label>Kimga</label>
            <select name="audience" id="aud" onchange="document.getElementById('p').style.display=this.value==='plan'?'block':'none';document.getElementById('u').style.display=this.value==='user'?'block':'none'">
                <option value="all" @selected(old('audience')==='all')>Hammaga</option>
                <option value="plan" @selected(old('audience')==='plan')>Tarif bo'yicha</option>
                <option value="user" @selected(old('audience')==='user')>Bitta foydalanuvchiga</option>
            </select></div>
        <div id="p" style="display:{{ old('audience')==='plan' ? 'block' : 'none' }}"><label>Tarif</label>
            <select name="plan">@foreach(['free'=>'Bepul','standard'=>'Standart','pro'=>'Pro'] as $k=>$v)<option value="{{ $k }}" @selected(old('plan')===$k)>{{ $v }}</option>@endforeach</select></div>
        <div id="u" style="display:{{ old('audience')==='user' ? 'block' : 'none' }}"><label>Telefon</label><input name="phone" value="{{ old('phone') }}" placeholder="+998901234567"></div>
    </div>
    <label style="display:flex;gap:6px;align-items:center"><input type="hidden" name="push" value="0"><input type="checkbox" name="push" value="1" checked style="width:auto"> Telefonga push-bildirishnoma ham yuborish</label>
    <p><button type="submit">Yuborish</button></p>
</form>

<h2>Yuborilganlar</h2>
<div class="scroll"><table>
<tr><th>Sana</th><th>Sarlavha</th><th>Matn</th><th>Kimga</th><th></th></tr>
@forelse($announcements as $a)
<tr>
    <td>{{ $a->created_at->format('d.m.Y H:i') }}</td><td><b>{{ $a->title }}</b></td><td>{{ \Illuminate\Support\Str::limit($a->body, 120) }}</td>
    <td>@if($a->audience==='all')Hammaga @elseif($a->audience==='plan')Tarif: {{ $a->plan }} @else<a href="{{ route('admin.users.show', $a->user_id) }}">{{ $a->user?->phone }}</a>@endif</td>
    <td><form method="post" action="{{ route('admin.announcements.destroy', $a->id) }}" onsubmit="return confirm('O\'chirilsinmi?')">@csrf @method('DELETE')<button class="red" type="submit">O'chirish</button></form></td>
</tr>
@empty<tr><td colspan="5" class="muted">Hozircha yo'q</td></tr>@endforelse
</table></div>
<div class="pager">{{ $announcements->links() }}</div>
@endsection
