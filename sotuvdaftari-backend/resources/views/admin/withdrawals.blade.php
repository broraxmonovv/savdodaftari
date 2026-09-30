@extends('admin.layout')
@section('title', 'Yechib olish')
@section('content')
<h1>Bonusni yechib olish so'rovlari</h1>
<p>
@foreach(['pending' => 'Kutilmoqda', 'paid' => "To'langan", 'rejected' => 'Rad etilgan', 'all' => 'Hammasi'] as $k => $v)
    <a class="badge {{ $status === $k ? 'b-green' : '' }}" href="{{ route('admin.withdrawals', ['status' => $k]) }}">{{ $v }}</a>
@endforeach
</p>
<div class="scroll"><table>
<tr><th>#</th><th>Sana</th><th>Foydalanuvchi</th><th>Summa</th><th>Karta</th><th>Holat</th><th>Amal</th></tr>
@forelse($withdrawals as $w)
<tr>
    <td>{{ $w->id }}</td><td>{{ $w->created_at->format('d.m.Y H:i') }}</td>
    <td><a href="{{ route('admin.users.show', $w->user_id) }}">{{ $w->user?->name ?: $w->user?->phone }}</a><div class="muted">{{ $w->user?->phone }}</div></td>
    <td><b>{{ number_format((float) $w->amount, 0, '.', ' ') }} so'm</b></td>
    <td><code>{{ trim(chunk_split($w->card_number, 4, ' ')) }}</code>@if($w->card_holder)<div class="muted">{{ $w->card_holder }}</div>@endif</td>
    <td><span class="badge {{ $w->status === 'paid' ? 'b-green' : ($w->status === 'pending' ? 'b-amb' : 'b-red') }}">{{ $w->status }}</span>@if($w->admin_note)<div class="muted">{{ $w->admin_note }}</div>@endif</td>
    <td>
        @if($w->isPending())
            <form method="post" action="{{ route('admin.withdrawals.paid', $w->id) }}" class="row" style="margin-bottom:6px" onsubmit="return confirm('Pul kartaga o\'tkazildimi?')">@csrf<input name="note" placeholder="Izoh" style="width:130px"><button type="submit">To'landi</button></form>
            <form method="post" action="{{ route('admin.withdrawals.reject', $w->id) }}" class="row" onsubmit="return confirm('Rad etilsinmi? Summa balansga qaytadi.')">@csrf<input name="note" placeholder="Sabab" style="width:130px"><button type="submit" class="red">Rad etish</button></form>
        @else<span class="muted">{{ $w->processed_at?->format('d.m.Y H:i') }}</span>@endif
    </td>
</tr>
@empty<tr><td colspan="7" class="muted">So'rovlar yo'q</td></tr>@endforelse
</table></div>
<div class="pager">{{ $withdrawals->links() }}</div>
@endsection
