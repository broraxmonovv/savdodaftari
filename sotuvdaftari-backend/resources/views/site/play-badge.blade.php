{{--
    Google Play tugmasi. Foydalanish: @include('site.play-badge', ['url' => $site['android_url'], 'lang' => $lang])
    Rasmiy badge (https://play.google.com/intl/en_us/badges/) faylini `public/brand/google-play-badge.svg` yoki `.png` nomi bilan
    qo'ysangiz, shu ishlatiladi; bo'lmasa quyidagi o'xshash tugma chiziladi. Havola bo'sh bo'lsa — "Tez orada".
--}}
@php
    $official = collect(['svg', 'png'])->first(fn ($e) => file_exists(public_path("brand/google-play-badge.$e")));
    $soon = $lang === 'ru' ? 'Скоро' : 'Tez orada';
    // Havola bor bo'lsa "Android uchun", yo'q bo'lsa "Tez orada"
    $label = $url ? __('site.hero.android') : $soon;
@endphp
@if($official)
    @if($url)<a class="play-official" href="{{ $url }}" rel="noopener" target="_blank"><img src="{{ asset("brand/google-play-badge.$official") }}" alt="Google Play — {{ $label }}" height="56"></a>
    @else<span class="play-official soon" title="{{ $soon }}"><img src="{{ asset("brand/google-play-badge.$official") }}" alt="Google Play" height="56"><em>{{ $soon }}</em></span>@endif
@else
    @php($tag = $url ? 'a' : 'span')
    <{{ $tag }} class="play-badge {{ $url ? '' : 'soon' }}" @if($url) href="{{ $url }}" rel="noopener" target="_blank" @endif aria-label="Google Play — {{ $label }}" title="{{ $label }}">
        <svg width="30" height="32" viewBox="0 0 512 512" aria-hidden="true" focusable="false">
            <polygon points="70,25 70,487 270,256" fill="#00b4ff"/>
            <polygon points="70,25 345,182 270,256" fill="#2fd27d"/>
            <polygon points="70,487 345,330 270,256" fill="#ff3a44"/>
            <polygon points="345,182 455,256 345,330 270,256" fill="#ffc400"/>
        </svg>
        <span><small>{{ $url ? ($lang === 'ru' ? 'Доступно в' : 'Yuklab oling') : $soon }}</small><b>Google Play</b></span>
    </{{ $tag }}>
@endif
