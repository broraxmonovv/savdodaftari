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
        :root { --g:#00a86b; --gd:#006b45; --gl:#e9f8f1; --tx:#1a1a1a; --mut:#6b747c; --bd:#e6eaed; --bg:#fff; --soft:#f7f9fa; }
        * { box-sizing:border-box; }
        html { scroll-behavior:smooth; }
        body { margin:0; font:16px/1.6 Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif; color:var(--tx); background:var(--bg); }
        a { color:inherit; text-decoration:none; }
        .container { width:min(1080px,92vw); margin:0 auto; }
        header { position:sticky; top:0; z-index:10; background:rgba(255,255,255,.92); backdrop-filter:blur(8px); border-bottom:1px solid var(--bd); }
        header .container { display:flex; align-items:center; justify-content:space-between; height:64px; gap:16px; }
        .logo { font-weight:800; font-size:20px; color:var(--gd); }
        .logo span { color:var(--g); }
        nav { display:flex; gap:22px; align-items:center; font-size:15px; }
        nav a:hover { color:var(--g); }
        .lang a { padding:4px 9px; border-radius:999px; font-size:13px; color:var(--mut); }
        .lang a.on { background:var(--gl); color:var(--gd); font-weight:600; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:13px 24px; border-radius:14px; background:var(--g); color:#fff; font-weight:600; border:0; cursor:pointer; font-size:16px; box-shadow:0 6px 18px rgba(0,168,107,.25); transition:transform .15s; }
        .btn:hover { transform:translateY(-1px); }
        .btn.ghost { background:#fff; color:var(--gd); border:1px solid var(--bd); box-shadow:none; }
        .btn.sm { padding:9px 16px; font-size:14px; border-radius:12px; box-shadow:none; }
        .hero { padding:72px 0 56px; background:radial-gradient(900px 400px at 85% -10%, var(--gl), transparent); }
        .hero .container { display:grid; grid-template-columns:1.2fr .8fr; gap:40px; align-items:center; }
        h1 { font-size:clamp(32px,5vw,52px); line-height:1.12; margin:0 0 18px; letter-spacing:-.02em; }
        .lead { font-size:18px; color:var(--mut); margin:0 0 28px; max-width:560px; }
        .cta { display:flex; gap:12px; flex-wrap:wrap; }
        .phone { justify-self:center; width:260px; background:#fff; border:1px solid var(--bd); border-radius:36px; padding:18px; box-shadow:0 24px 60px rgba(16,24,40,.12); }
        .phone .card { background:var(--soft); border-radius:18px; padding:14px; margin-bottom:10px; }
        .phone .card b { display:block; font-size:20px; } .phone small { color:var(--mut); }
        .phone .pill { background:var(--g); color:#fff; border-radius:14px; text-align:center; padding:10px; font-weight:600; }
        section { padding:64px 0; }
        section.alt { background:var(--soft); }
        h2 { font-size:clamp(26px,3.5vw,36px); margin:0 0 10px; letter-spacing:-.01em; text-align:center; }
        .sub { text-align:center; color:var(--mut); margin:0 auto 36px; max-width:600px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:18px; }
        .card { background:#fff; border:1px solid var(--bd); border-radius:20px; padding:24px; box-shadow:0 8px 24px rgba(16,24,40,.05); }
        .card .ic { width:48px; height:48px; border-radius:14px; background:var(--gl); display:grid; place-items:center; font-size:24px; margin-bottom:14px; }
        .card h3 { margin:0 0 6px; font-size:18px; } .card p { margin:0; color:var(--mut); }
        .step { text-align:center; } .step .n { width:44px; height:44px; border-radius:50%; background:var(--g); color:#fff; font-weight:700; display:grid; place-items:center; margin:0 auto 12px; }
        .plan h3 { font-size:20px; } .plan .price { font-size:34px; font-weight:800; margin:8px 0 2px; } .plan .price small { font-size:14px; font-weight:500; color:var(--mut); }
        .plan ul { list-style:none; padding:0; margin:16px 0 0; } .plan li { padding:6px 0 6px 26px; position:relative; color:var(--tx); }
        .plan li::before { content:'✓'; position:absolute; left:0; color:var(--g); font-weight:700; }
        .plan.best { border:2px solid var(--g); position:relative; } .plan .tag { position:absolute; top:-12px; right:20px; background:var(--g); color:#fff; font-size:12px; padding:3px 12px; border-radius:999px; }
        .ref { background:linear-gradient(135deg,var(--gd),var(--g)); color:#fff; border-radius:24px; padding:40px; text-align:center; }
        .ref h2 { color:#fff; } .ref p { max-width:640px; margin:0 auto; opacity:.92; }
        details { background:#fff; border:1px solid var(--bd); border-radius:16px; padding:16px 20px; margin-bottom:10px; }
        summary { cursor:pointer; font-weight:600; } details p { margin:10px 0 0; color:var(--mut); }
        form.lead { max-width:560px; margin:0 auto; }
        label { display:block; font-size:14px; font-weight:600; margin:14px 0 6px; }
        input, select, textarea { width:100%; font:inherit; padding:13px 14px; border:1px solid var(--bd); border-radius:14px; background:#fff; }
        input:focus, select:focus, textarea:focus { outline:2px solid var(--gl); border-color:var(--g); }
        textarea { min-height:90px; resize:vertical; }
        .check { display:flex; gap:10px; align-items:flex-start; font-weight:400; color:var(--mut); } .check input { width:auto; margin-top:5px; }
        .hp { position:absolute; left:-9999px; height:0; overflow:hidden; }
        .ok { background:var(--gl); color:var(--gd); padding:14px 18px; border-radius:14px; margin-bottom:16px; text-align:center; font-weight:600; }
        .err { background:#fdecea; color:#e53935; padding:12px 16px; border-radius:14px; margin-bottom:12px; }
        footer { border-top:1px solid var(--bd); padding:32px 0; color:var(--mut); font-size:14px; }
        footer .container { display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px; }
        @media (max-width:860px) { .hero .container { grid-template-columns:1fr; } .phone { display:none; } nav .links { display:none; } .hero { padding-top:40px; } }
    </style>
</head>
<body>
<header>
    <div class="container">
        <a class="logo" href="{{ route('site.home', ['lang' => $lang]) }}">Bozor<span>Pro</span></a>
        <nav>
            <span class="links" style="display:flex;gap:22px">
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
                <h1>{{ __('site.hero.title') }}</h1>
                <p class="lead">{{ __('site.hero.subtitle') }}</p>
                <div class="cta">
                    @if($site['android_url'])<a class="btn" href="{{ $site['android_url'] }}">▶ {{ __('site.hero.android') }}</a>@endif
                    @if($site['ios_url'])<a class="btn" href="{{ $site['ios_url'] }}">  {{ __('site.hero.ios') }}</a>@endif
                    <a class="btn {{ ($site['android_url'] || $site['ios_url']) ? 'ghost' : '' }}" href="#contact">{{ __('site.hero.cta_request') }}</a>
                </div>
            </div>
            <div class="phone" aria-hidden="true">
                <div class="card"><small>{{ $lang === 'ru' ? 'Продажи сегодня' : 'Bugungi savdo' }}</small><b>3 450 000</b></div>
                <div class="card"><small>{{ $lang === 'ru' ? 'Прибыль' : 'Foyda' }}</small><b style="color:var(--g)">620 000</b></div>
                <div class="card"><small>{{ $lang === 'ru' ? 'Долги' : 'Qarzlar' }}</small><b style="color:#e53935">1 280 000</b></div>
                <div class="pill">+ {{ $lang === 'ru' ? 'Продажа' : 'Savdo' }}</div>
            </div>
        </div>
    </div>

    <section id="features">
        <div class="container">
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
