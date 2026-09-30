@extends('admin.layout')
@section('title', 'Tariflar')
@section('content')
<h1>Tariflar</h1>
<p class="muted">Narx va muddat o'zgarganda mobil ilova (GET /billing/plan) va yangi checkout'lar darhol yangi qiymatni oladi. Allaqachon yaratilgan kutilayotgan to'lovlar eski narxda qoladi. Faol bo'lmagan tarif sotuvdan olinadi (mavjud obunalar ishlashda davom etadi).</p>
<div class="two">
@foreach($plans as $plan)
    <form method="post" action="{{ route('admin.plans.update', $plan->key) }}" class="card">
        @csrf @method('PUT')
        <h2 style="margin-top:0">{{ $plan->key === 'pro' ? 'Pro' : 'Standart' }}
            <span class="badge {{ $plan->is_active ? 'b-green' : 'b-red' }}">{{ $plan->is_active ? 'sotuvda' : 'o\'chirilgan' }}</span></h2>
        <label>Narx (so'm)</label><input type="number" name="price" value="{{ old('price', $plan->price) }}" min="1000" max="100000000" step="1000" required>
        <label>Muddat (kun)</label><input type="number" name="days" value="{{ old('days', $plan->days) }}" min="1" max="3650" required>
        <label><input type="checkbox" name="is_active" value="1" @checked($plan->is_active) style="width:auto"> Sotuvda (faol)</label>
        <p class="muted">Imkoniyatlar: {{ implode(', ', $plan->features ?? []) }}</p>
        <button type="submit">Saqlash</button>
        <span class="muted">Yangilangan: {{ $plan->updated_at->format('d.m.Y H:i') }}</span>
    </form>
@endforeach
</div>
@endsection
