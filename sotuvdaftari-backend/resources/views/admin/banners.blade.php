@extends('admin.layout')
@section('title', 'Reklama')
@section('content')
<h1>Reklama bannerlari</h1>
<p class="muted">Ilovaning bosh sahifasidagi karuselda ko'rsatiladi. Rasmga bosilganda foydalanuvchi havolaga o'tadi. Tavsiya: 1200×500 px.</p>

<form method="post" action="{{ route('admin.banners.store') }}" enctype="multipart/form-data" class="card" style="max-width:640px">
    @csrf
    <label>Sarlavha</label><input name="title" value="{{ old('title') }}" maxlength="150" style="width:100%" required>
    <label>Havola (URL)</label><input name="url" type="url" value="{{ old('url') }}" placeholder="https://example.uz" style="width:100%" required>
    <label>Rasm</label><input name="image" type="file" accept="image/*" required>
    <div class="row">
        <div><label>Necha kun ko'rsatilsin (bo'sh = cheksiz)</label><input name="days" type="number" min="1" max="3650" value="{{ old('days', 30) }}"></div>
        <div><label>Tartib raqami</label><input name="sort" type="number" min="0" value="{{ old('sort', 0) }}"></div>
    </div>
    <p><button type="submit">Qo'shish</button></p>
</form>

<h2>Bannerlar</h2>
<div class="scroll"><table>
<tr><th>Rasm</th><th>Sarlavha / havola</th><th>Muddat</th><th>Bosishlar</th><th>Holat</th><th></th></tr>
@forelse($banners as $b)
<tr>
    <td><img src="{{ $b->image_url }}" alt="" style="width:160px;height:66px;object-fit:cover;border-radius:10px"></td>
    <td><b>{{ $b->title }}</b><br><a href="{{ $b->url }}" target="_blank" rel="noopener" class="muted">{{ \Illuminate\Support\Str::limit($b->url, 50) }}</a></td>
    <td>{{ $b->starts_at?->format('d.m.Y') }} — {{ $b->ends_at ? $b->ends_at->format('d.m.Y') : 'cheksiz' }}</td>
    <td>{{ $b->clicks }}</td>
    <td>
        @if($b->isVisible())<span style="color:#16a34a;font-weight:600">Ko'rsatilmoqda</span>
        @elseif(! $b->is_active)<span class="muted">O'chirilgan</span>
        @else<span style="color:#dc2626">Muddati tugagan</span>@endif
    </td>
    <td style="white-space:nowrap">
        <form method="post" action="{{ route('admin.banners.update', $b->id) }}" style="display:inline">@csrf @method('PUT')
            <input type="hidden" name="is_active" value="{{ $b->is_active ? 0 : 1 }}">
            <button class="sec" type="submit">{{ $b->is_active ? 'O\'chirib qo\'yish' : 'Yoqish' }}</button></form>
        <form method="post" action="{{ route('admin.banners.update', $b->id) }}" style="display:inline">@csrf @method('PUT')
            <input name="days" type="number" min="1" placeholder="kun" style="width:70px" required>
            <button class="sec" type="submit">Uzaytirish</button></form>
        <form method="post" action="{{ route('admin.banners.destroy', $b->id) }}" style="display:inline" onsubmit="return confirm('O\'chirilsinmi?')">@csrf @method('DELETE')<button class="red" type="submit">O'chirish</button></form>
    </td>
</tr>
@empty<tr><td colspan="6" class="muted">Hozircha banner yo'q</td></tr>@endforelse
</table></div>
@endsection
