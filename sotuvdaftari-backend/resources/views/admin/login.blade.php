<!doctype html>
<html lang="uz">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kirish — Savdo Up Admin</title>
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:#0f1f19; font:14px system-ui,sans-serif; }
        form { background:#fff; padding:28px; border-radius:16px; width:min(360px,92vw); }
        h1 { margin:0 0 16px; font-size:20px; } label { display:block; font-size:12px; color:#7a828a; margin:12px 0 4px; }
        input { width:100%; padding:10px; border:1px solid #e6eaed; border-radius:8px; font:inherit; }
        button { margin-top:18px; width:100%; padding:11px; border:0; border-radius:8px; background:#00a86b; color:#fff; font:inherit; cursor:pointer; }
        .err { background:#fdecea; color:#e53935; padding:9px 12px; border-radius:8px; margin-bottom:8px; }
    </style>
</head>
<body>
<form method="post" action="{{ route('admin.login.submit') }}">
    @csrf
    <h1>Savdo Up Admin</h1>
    @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
    <label>Telefon</label><input name="phone" value="{{ old('phone') }}" placeholder="+998901234567" autofocus required>
    <label>Parol</label><input type="password" name="password" required>
    <button type="submit">Kirish</button>
</form>
</body>
</html>
