<!doctype html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') — BozorPro</title>
    <style>
        :root { --g:#00a86b; --gd:#006b45; --bg:#f4f6f8; --card:#fff; --tx:#1a1a1a; --mut:#7a828a; --bd:#e6eaed; --red:#e53935; --amb:#f5a623; --blue:#2f80ed; }
        * { box-sizing: border-box; }
        body { margin:0; font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--bg); color:var(--tx); }
        a { color:var(--gd); text-decoration:none; } a:hover { text-decoration:underline; }
        .wrap { display:flex; min-height:100vh; }
        nav { width:220px; background:#0f1f19; color:#cfe; padding:20px 0; flex-shrink:0; }
        nav .brand { padding:0 20px 18px; font-weight:700; font-size:18px; color:#fff; }
        nav a { display:block; padding:10px 20px; color:#b8d6c8; }
        nav a.on, nav a:hover { background:#16352a; color:#fff; text-decoration:none; }
        nav form { padding:16px 20px 0; }
        main { flex:1; padding:24px; min-width:0; }
        h1 { margin:0 0 18px; font-size:22px; } h2 { margin:24px 0 10px; font-size:16px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px; }
        .card { background:var(--card); border:1px solid var(--bd); border-radius:12px; padding:16px; }
        .stat .v { font-size:24px; font-weight:700; margin-top:4px; } .stat .l { color:var(--mut); font-size:12px; }
        .muted { color:var(--mut); }
        table { width:100%; border-collapse:collapse; background:var(--card); border:1px solid var(--bd); border-radius:12px; overflow:hidden; }
        th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--bd); vertical-align:top; }
        th { background:#fafbfc; font-size:12px; color:var(--mut); text-transform:uppercase; letter-spacing:.03em; }
        tr:last-child td { border-bottom:0; }
        .scroll { overflow-x:auto; }
        .badge { display:inline-block; padding:2px 9px; border-radius:999px; font-size:12px; background:#eef1f3; }
        .b-green { background:#e3f6ee; color:var(--gd); } .b-red { background:#fdecea; color:var(--red); }
        .b-amb { background:#fff4dd; color:#a66a00; } .b-blue { background:#eaf2fe; color:var(--blue); }
        input, select, textarea { font:inherit; padding:8px 10px; border:1px solid var(--bd); border-radius:8px; background:#fff; color:var(--tx); }
        textarea { width:100%; min-height:90px; }
        label { display:block; font-size:12px; color:var(--mut); margin:10px 0 4px; }
        button, .btn { font:inherit; padding:8px 14px; border:0; border-radius:8px; background:var(--g); color:#fff; cursor:pointer; display:inline-block; }
        button.sec { background:#e9f8f1; color:var(--gd); } button.red { background:var(--red); } button.amb { background:var(--amb); }
        .row { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }
        .flash { padding:10px 14px; border-radius:8px; margin-bottom:14px; background:#e3f6ee; color:var(--gd); }
        .err { padding:10px 14px; border-radius:8px; margin-bottom:14px; background:#fdecea; color:var(--red); }
        .two { display:grid; grid-template-columns:repeat(auto-fit,minmax(340px,1fr)); gap:16px; }
        .bars { display:flex; align-items:flex-end; gap:6px; height:120px; }
        .bars div { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; font-size:10px; color:var(--mut); }
        .bars i { display:block; width:100%; background:var(--g); border-radius:4px 4px 0 0; min-height:2px; }
        .bars i.rev { background:var(--blue); }
        .pager { margin-top:14px; } .pager nav { all:unset; } .pager svg { width:16px; }
        @media (max-width:800px) { .wrap { flex-direction:column; } nav { width:auto; display:flex; flex-wrap:wrap; padding:10px; } nav .brand { width:100%; padding:4px 10px; } nav a { padding:8px 12px; } }
    </style>
</head>
<body>
@php($menu = ['admin.dashboard' => 'Dashboard', 'admin.users' => 'Foydalanuvchilar', 'admin.plans' => 'Tariflar', 'admin.withdrawals' => 'Yechib olish', 'admin.leads' => 'Arizalar', 'admin.announcements' => 'Bildirishnomalar'])
<div class="wrap">
    <nav>
        <div class="brand">BozorPro Admin</div>
        @foreach($menu as $route => $label)
            <a href="{{ route($route) }}" class="{{ request()->routeIs($route.'*') ? 'on' : '' }}">{{ $label }}</a>
        @endforeach
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="sec" type="submit">Chiqish</button></form>
    </nav>
    <main>
        @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>
</body>
</html>
