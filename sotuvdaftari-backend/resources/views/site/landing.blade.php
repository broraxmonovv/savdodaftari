<!doctype html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('site.brand') }} — {{ __('site.hero.title') }}</title>
    <meta name="description" content="{{ __('site.hero.subtitle') }}">
    <meta property="og:title" content="{{ __('site.brand') }} — {{ __('site.hero.title') }}">
    <meta property="og:description" content="{{ __('site.hero.subtitle') }}">
    <style>
        :root { --g:#00a86b; --gd:#006b45; --gl:#e9f8f1; --tx:#14201b; --mut:#64727a; --bd:#e6eaed; --bg:#fff; --soft:#f6f9f8; --r:22px; }
        * { box-sizing:border-box; }
        html { scroll-behavior:smooth; }
        body { margin:0; font:16px/1.65 Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif; color:var(--tx); background:var(--bg); -webkit-font-smoothing:antialiased; }
        a { color:inherit; text-decoration:none; }
        img { max-width:100%; height:auto; display:block; }
        .container { width:min(1120px,92vw); margin:0 auto; }
        header { position:sticky; top:0; z-index:20; background:rgba(255,255,255,.85); backdrop-filter:saturate(180%) blur(14px); border-bottom:1px solid rgba(230,234,237,.8); }
        header .container { display:flex; align-items:center; justify-content:space-between; height:68px; gap:16px; }
        .logo { display:flex; align-items:center; gap:10px; font-weight:800; font-size:20px; color:var(--gd); letter-spacing:-.02em; }
        .logo i { width:34px; height:34px; border-radius:11px; background:linear-gradient(135deg,var(--g),var(--gd)); display:grid; place-items:center; color:#fff; font-style:normal; font-size:18px; box-shadow:0 6px 14px rgba(0,168,107,.35); }
        nav { display:flex; gap:22px; align-items:center; font-size:15px; }
        nav a:hover { color:var(--g); }
        nav .links { display:flex; gap:22px; }
        .logo { white-space:nowrap; }
        .lang a { padding:4px 9px; border-radius:999px; font-size:13px; color:var(--mut); }
        .lang a.on { background:var(--gl); color:var(--gd); font-weight:600; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:14px 26px; border-radius:16px; background:linear-gradient(135deg,var(--g),#00c47d); color:#fff; font-weight:600; border:0; cursor:pointer; font-size:16px; box-shadow:0 10px 24px rgba(0,168,107,.3); transition:transform .15s, box-shadow .15s; }
        .btn:hover { transform:translateY(-2px); box-shadow:0 14px 28px rgba(0,168,107,.35); }
        .btn.ghost { background:#fff; color:var(--gd); border:1px solid var(--bd); box-shadow:none; }
        .btn.sm { white-space:nowrap; padding:10px 18px; font-size:14px; border-radius:12px; box-shadow:none; }
        /* Hero */
        .hero { position:relative; overflow:hidden; padding:72px 0 88px; background:radial-gradient(800px 420px at 88% -5%, #d6f5e7, transparent), radial-gradient(600px 360px at -5% 30%, #eef9f4, transparent); }
        .hero .container { display:grid; grid-template-columns:1.1fr .9fr; gap:48px; align-items:center; }
        .badge { display:inline-flex; align-items:center; gap:8px; padding:6px 14px; border-radius:999px; background:#fff; border:1px solid var(--bd); color:var(--gd); font-size:13px; font-weight:600; margin-bottom:20px; box-shadow:0 4px 12px rgba(16,24,40,.05); }
        .badge::before { content:''; width:8px; height:8px; border-radius:50%; background:var(--g); box-shadow:0 0 0 4px rgba(0,168,107,.18); }
        h1 { font-size:clamp(34px,5.2vw,58px); line-height:1.08; margin:0 0 20px; letter-spacing:-.03em; }
        h1 em { font-style:normal; background:linear-gradient(135deg,var(--g),var(--gd)); -webkit-background-clip:text; background-clip:text; color:transparent; }
        .lead { font-size:19px; color:var(--mut); margin:0 0 30px; max-width:560px; }
        .cta { display:flex; gap:12px; flex-wrap:wrap; }
        .stats { display:flex; gap:32px; margin-top:38px; flex-wrap:wrap; }
        .stats b { display:block; font-size:26px; letter-spacing:-.02em; } .stats span { color:var(--mut); font-size:14px; }
        /* Telefon ramkasi (ilovaning haqiqiy ekrani) */
        .device { position:relative; justify-self:center; width:min(300px,78vw); }
        .device::before { content:''; position:absolute; inset:8% -12% -6% -12%; background:radial-gradient(closest-side, rgba(0,168,107,.28), transparent); filter:blur(30px); z-index:0; }
        .frame { position:relative; z-index:1; background:#0f1a15; border-radius:44px; padding:10px; box-shadow:0 40px 80px rgba(16,24,40,.28), inset 0 0 0 2px #27332d; }
        .frame img { border-radius:34px; width:100%; background:#f7fafb; }
        .chip { position:absolute; z-index:3; background:#fff; border:1px solid var(--bd); border-radius:16px; padding:10px 14px; box-shadow:0 16px 36px rgba(16,24,40,.14); display:flex; align-items:center; gap:10px; font-size:13px; font-weight:600; animation:float 6s ease-in-out infinite; }
        .chip i { width:30px; height:30px; border-radius:10px; background:var(--gl); display:grid; place-items:center; font-style:normal; }
        .chip.a { left:-48px; top:22%; } .chip.b { right:-40px; bottom:26%; animation-delay:-3s; }
        @keyframes float { 0%,100% { transform:translateY(0); } 50% { transform:translateY(-10px); } }
        @media (prefers-reduced-motion:reduce) { .chip { animation:none; } html { scroll-behavior:auto; } }
        section { padding:84px 0; }
        section.alt { background:var(--soft); }
        .eyebrow { display:block; text-align:center; color:var(--g); font-weight:700; font-size:13px; letter-spacing:.12em; text-transform:uppercase; margin-bottom:10px; }
        h2 { font-size:clamp(28px,3.6vw,40px); line-height:1.15; margin:0 0 12px; letter-spacing:-.02em; text-align:center; }
        .sub { text-align:center; color:var(--mut); margin:0 auto 44px; max-width:600px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:20px; }
        .card { background:#fff; border:1px solid var(--bd); border-radius:var(--r); padding:26px; box-shadow:0 10px 30px rgba(16,24,40,.05); transition:transform .2s, box-shadow .2s; }
        .grid > .card:hover { transform:translateY(-4px); box-shadow:0 18px 40px rgba(16,24,40,.09); }
        .card .ic { width:52px; height:52px; border-radius:16px; background:linear-gradient(135deg,var(--gl),#d3f3e5); display:grid; place-items:center; font-size:26px; margin-bottom:16px; }
        .card h3 { margin:0 0 6px; font-size:18px; } .card p { margin:0; color:var(--mut); }
        .step { text-align:center; padding:8px 12px; } .step .n { width:48px; height:48px; border-radius:16px; background:linear-gradient(135deg,var(--g),var(--gd)); color:#fff; font-weight:700; font-size:20px; display:grid; place-items:center; margin:0 auto 14px; box-shadow:0 10px 20px rgba(0,168,107,.3); }
        .step h3 { margin:0 0 4px; }
        .plan h3 { font-size:20px; margin:0; } .plan .price { font-size:38px; font-weight:800; margin:10px 0 2px; letter-spacing:-.03em; } .plan .price small { font-size:14px; font-weight:500; color:var(--mut); letter-spacing:0; }
        .plan ul { list-style:none; padding:0; margin:18px 0 0; } .plan li { padding:7px 0 7px 28px; position:relative; }
        .plan li::before { content:'✓'; position:absolute; left:0; top:7px; width:20px; height:20px; border-radius:50%; background:var(--gl); color:var(--g); font-size:12px; font-weight:700; display:grid; place-items:center; }
        .plan.best { border:2px solid var(--g); position:relative; box-shadow:0 20px 50px rgba(0,168,107,.18); } .plan .tag { position:absolute; top:-13px; right:22px; background:linear-gradient(135deg,var(--g),var(--gd)); color:#fff; font-size:12px; font-weight:700; padding:4px 14px; border-radius:999px; }
        .ref { position:relative; overflow:hidden; background:linear-gradient(135deg,var(--gd),var(--g)); color:#fff; border-radius:28px; padding:54px 32px; text-align:center; }
        .ref::after { content:''; position:absolute; width:320px; height:320px; right:-80px; top:-120px; border-radius:50%; background:rgba(255,255,255,.12); }
        .ref h2 { color:#fff; } .ref p { max-width:640px; margin:0 auto; opacity:.94; font-size:18px; position:relative; z-index:1; }
        details { background:#fff; border:1px solid var(--bd); border-radius:18px; padding:18px 22px; margin-bottom:12px; transition:box-shadow .2s; }
        details[open] { box-shadow:0 10px 26px rgba(16,24,40,.07); }
        summary { cursor:pointer; font-weight:600; } details p { margin:10px 0 0; color:var(--mut); }
        form.lead { max-width:560px; margin:0 auto; }
        label { display:block; font-size:14px; font-weight:600; margin:14px 0 6px; }
        input, select, textarea { width:100%; font:inherit; padding:14px 15px; border:1px solid var(--bd); border-radius:14px; background:#fff; }
        input:focus, select:focus, textarea:focus { outline:3px solid var(--gl); border-color:var(--g); }
        textarea { min-height:90px; resize:vertical; }
        .check { display:flex; gap:10px; align-items:flex-start; font-weight:400; color:var(--mut); } .check input { width:auto; margin-top:5px; }
        .hp { position:absolute; left:-9999px; height:0; overflow:hidden; }
        .ok { background:var(--gl); color:var(--gd); padding:14px 18px; border-radius:14px; margin-bottom:16px; text-align:center; font-weight:600; }
        .err { background:#fdecea; color:#e53935; padding:12px 16px; border-radius:14px; margin-bottom:12px; }
        footer { border-top:1px solid var(--bd); padding:36px 0; color:var(--mut); font-size:14px; }
        footer .container { display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px; }
        @media (max-width:900px) { .hero .container { grid-template-columns:1fr; text-align:center; } .lead { margin-inline:auto; } .cta, .stats { justify-content:center; } nav .links { display:none; } .hero { padding:44px 0 64px; } .chip.a { left:-14px; } .chip.b { right:-14px; } section { padding:60px 0; } }
    </style>
</head>
<body>
<header>
    <div class="container">
        <a class="logo" href="{{ route('site.home', ['lang' => $lang]) }}"><i>↑</i>{{ __('site.brand') }}</a>
        <nav>
            <span class="links">
                <a href="#features">{{ __('site.nav.features') }}</a>
                <a href="#plans">{{ __('site.nav.plans') }}</a>
                <a href="#faq">{{ __('site.nav.faq') }}</a>
            </span>
            <span class="lang">
                <a href="?lang=uz" class="{{ $lang === 'uz' ? 'on' : '' }}">UZ</a><a href="?lang=ru" class="{{ $lang === 'ru' ? 'on' : '' }}">RU</a>
            </span>
            <a class="btn sm" href="#contact">{{ __('site.nav.contact') }}</a>
        </nav>
    </div>
</header>

<main>
    <div class="hero">
        <div class="container">
            <div>
                <span class="badge">{{ $lang === 'ru' ? 'Для рынка и магазина' : 'Bozor va do\'kon uchun' }}</span>
                <h1>{{ __('site.hero.title') }}</h1>
                <p class="lead">{{ __('site.hero.subtitle') }}</p>
                <div class="cta">
                    @if($site['android_url'])<a class="btn" href="{{ $site['android_url'] }}">▶ {{ __('site.hero.android') }}</a>@endif
                    @if($site['ios_url'])<a class="btn" href="{{ $site['ios_url'] }}">  {{ __('site.hero.ios') }}</a>@endif
                    <a class="btn {{ ($site['android_url'] || $site['ios_url']) ? 'ghost' : '' }}" href="#contact">{{ __('site.hero.cta_request') }}</a>
                </div>
                <div class="stats">
                    <div><b>14 {{ $lang === 'ru' ? 'дней' : 'kun' }}</b><span>{{ $lang === 'ru' ? 'бесплатно Стандарт' : 'bepul Standart' }}</span></div>
                    <div><b>UZ · RU</b><span>{{ $lang === 'ru' ? 'два языка' : 'ikki til' }}</span></div>
                    <div><b>🎙️</b><span>{{ $lang === 'ru' ? 'голосовой ввод' : 'ovozli kiritish' }}</span></div>
                </div>
            </div>
            <div class="device">
                <span class="chip a"><i>📈</i>{{ $lang === 'ru' ? 'Прибыль растёт' : 'Foyda o\'smoqda' }}</span>
                <div class="frame">
                    <img src="{{ asset('site/app-home.webp') }}" width="640" height="1288" alt="{{ __('site.brand') }} — {{ $lang === 'ru' ? 'главный экран приложения' : 'ilovaning bosh sahifasi' }}" fetchpriority="high">
                </div>
                <span class="chip b"><i>🔔</i>{{ $lang === 'ru' ? 'Напоминание о долге' : 'Qarz eslatmasi' }}</span>
            </div>
        </div>
    </div>

    <section id="features">
        <div class="container">
            <span class="eyebrow">{{ $lang === 'ru' ? 'Возможности' : 'Imkoniyatlar' }}</span>
            <h2>{{ __('site.features.title') }}</h2>
            <p class="sub"></p>
            <div class="grid">
                @foreach(__('site.features.items') as $item)
                    <div class="card"><div class="ic">{{ $item['icon'] }}</div><h3>{{ $item['title'] }}</h3><p>{{ $item['text'] }}</p></div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="alt">
        <div class="container">
            <span class="eyebrow">{{ $lang === 'ru' ? 'Быстрый старт' : 'Tez boshlash' }}</span>
            <h2>{{ __('site.steps.title') }}</h2>
            <p class="sub"></p>
            <div class="grid">
                @foreach(__('site.steps.items') as $i => $step)
                    <div class="step"><div class="n">{{ $i + 1 }}</div><h3>{{ $step['title'] }}</h3><p style="color:var(--mut);margin:0">{{ $step['text'] }}</p></div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="plans">
        <div class="container">
            <span class="eyebrow">{{ $lang === 'ru' ? 'Цены' : 'Narxlar' }}</span>
            <h2>{{ __('site.plans.title') }}</h2>
            <p class="sub">{{ __('site.plans.subtitle') }}</p>
            <div class="grid">
                <div class="card plan">
                    <h3>{{ __('site.plans.free') }}</h3>
                    <div class="price">0 <small>{{ __('site.plans.currency') }}</small></div>
                    <ul>@foreach(__('site.plans.free_features') as $f)<li>{{ $f }}</li>@endforeach</ul>
                </div>
                @foreach(['standard', 'pro'] as $key)
                    @if(isset($plans[$key]))
                        <div class="card plan {{ $key === 'pro' ? 'best' : '' }}">
                            @if($key === 'pro')<span class="tag">Top</span>@endif
                            <h3>{{ __('site.plans.'.$key) }}</h3>
                            <div class="price">{{ number_format($plans[$key]['price'], 0, '.', ' ') }} <small>{{ __('site.plans.currency') }} / {{ __('site.plans.per', ['days' => $plans[$key]['days']]) }}</small></div>
                            <ul>@foreach(__('site.plans.'.$key.'_features') as $f)<li>{{ $f }}</li>@endforeach</ul>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </section>

    <section class="alt">
        <div class="container">
            <div class="ref">
                <h2>{{ __('site.referral.title') }}</h2>
                <p>{{ __('site.referral.text', ['percent' => rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.')]) }}</p>
            </div>
        </div>
    </section>

    <section id="faq">
        <div class="container" style="max-width:760px">
            <h2>{{ __('site.faq.title') }}</h2>
            <p class="sub"></p>
            @foreach(__('site.faq.items') as $qa)
                <details><summary>{{ $qa['q'] }}</summary><p>{{ $qa['a'] }}</p></details>
            @endforeach
        </div>
    </section>

    <section id="contact" class="alt">
        <div class="container">
            <h2>{{ __('site.form.title') }}</h2>
            <p class="sub">{{ __('site.form.subtitle') }}</p>

            <form class="lead card" method="post" action="{{ route('site.lead') }}">
                @csrf
                <input type="hidden" name="lang" value="{{ $lang }}">
                @if(session('lead_sent'))<div class="ok">{{ __('site.form.success') }}</div>@endif
                @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif

                <div class="hp" aria-hidden="true"><label>Website</label><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

                <label for="name">{{ __('site.form.name') }}</label>
                <input id="name" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name">

                <label for="phone">{{ __('site.form.phone') }}</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="{{ __('site.form.phone_hint') }}" required autocomplete="tel">

                <label for="business_type">{{ __('site.form.business') }}</label>
                <select id="business_type" name="business_type">
                    <option value="">—</option>
                    @foreach(__('site.form.business_options') as $k => $v)<option value="{{ $k }}" @selected(old('business_type') === $k)>{{ $v }}</option>@endforeach
                </select>

                <label for="message">{{ __('site.form.message') }}</label>
                <textarea id="message" name="message" maxlength="1000">{{ old('message') }}</textarea>

                <label class="check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))><span>{{ __('site.form.consent') }}</span></label>

                <p style="margin:18px 0 0"><button class="btn" type="submit" style="width:100%">{{ __('site.form.submit') }}</button></p>
            </form>
        </div>
    </section>
</main>

<footer>
    <div class="container">
        <div>© {{ date('Y') }} {{ __('site.brand') }}. {{ __('site.footer.rights') }}</div>
        <div>
            {{ __('site.footer.contact') }}:
            @if($support['phone'])<a href="tel:{{ $support['phone'] }}">{{ $support['phone'] }}</a> · @endif
            @if($support['telegram'])<a href="{{ $support['telegram'] }}">Telegram</a> · @endif
            @if($support['email'])<a href="mailto:{{ $support['email'] }}">{{ $support['email'] }}</a>@endif
            @if($support['working_hours'])<br>{{ __('site.footer.hours') }}: {{ $support['working_hours'] }}@endif
        </div>
    </div>
</footer>
</body>
</html>
