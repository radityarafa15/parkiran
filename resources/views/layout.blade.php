<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Parkiran') · {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #f6f7f9; --card: #fff; --ink: #1d232b; --muted: #5f6b7a; --line: #dfe3e8;
            --available: #1f9d55; --occupied: #d64545; --unknown: #9aa5b1; --busy: #d9822b; --reserved: #3b6fd8;
        }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #14181d; --card: #1d232b; --ink: #eef1f4; --muted: #a3adb9; --line: #2e3640; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: var(--bg); color: var(--ink); }
        main { max-width: 960px; margin: 0 auto; padding: 24px 16px 48px; }
        a { color: inherit; }
        h1 { font-size: 1.5rem; margin: 0 0 4px; }
        .muted { color: var(--muted); }
        .floors { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-top: 24px; }
        .floor-card { display: block; text-decoration: none; background: var(--card); border: 1px solid var(--line); border-left: 6px solid var(--available); border-radius: 12px; padding: 20px; }
        .floor-card[data-level="busy"] { border-left-color: var(--busy); }
        .floor-card[data-level="full"] { border-left-color: var(--occupied); }
        .floor-card h2 { margin: 0 0 8px; font-size: 1.15rem; }
        .big { font-size: 2.25rem; font-weight: 700; }
        .legend { display: flex; flex-wrap: wrap; gap: 16px; margin: 16px 0; font-size: .9rem; }
        .dot { display: inline-block; width: 12px; height: 12px; border-radius: 3px; margin-right: 6px; vertical-align: -1px; }
        .zones { display: flex; flex-wrap: wrap; gap: 8px; margin: 16px 0 24px; }
        .zone-chip { text-decoration: none; background: var(--card); border: 1px solid var(--line); border-radius: 999px; padding: 6px 12px; font-size: .9rem; }
        .zone-chip[data-full="1"] { opacity: .55; }
        .zone h2 { font-size: 1rem; margin: 24px 0 8px; }
        .slots { display: grid; gap: 4px; grid-template-columns: repeat(auto-fill, minmax(52px, 1fr)); }
        .slot { border-radius: 6px; padding: 8px 2px; text-align: center; font-weight: 600; font-size: .75rem; color: #fff; }
        .s-available { background: var(--available); }
        .s-occupied { background: var(--occupied); }
        .s-unknown { background: var(--unknown); }
        .s-reserved { background: var(--reserved); }
        a.slot { text-decoration: none; pointer-events: none; }
        a.slot.s-available { pointer-events: auto; }
        a.slot.s-available:hover { outline: 2px solid var(--ink); }
        .flash { border-radius: 8px; padding: 10px 12px; margin: 12px 0; border: 1px solid var(--line); background: var(--card); }
        .flash.ok { border-left: 6px solid var(--available); }
        .flash.err { border-left: 6px solid var(--occupied); }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 20px; margin-top: 16px; }
        .narrow { max-width: 480px; }
        .facts { display: grid; grid-template-columns: auto 1fr; gap: 6px 16px; margin: 0; }
        .facts dt { color: var(--muted); }
        .facts dd { margin: 0; font-weight: 600; }
        .qr { display: block; width: 240px; max-width: 100%; margin: 8px auto; background: #fff; border-radius: 8px; }
        .qr-code { text-align: center; font-family: ui-monospace, monospace; font-size: 1.25rem; letter-spacing: 2px; font-weight: 700; }
        .badge { display: inline-block; border-radius: 999px; padding: 2px 10px; font-size: .85rem; font-weight: 600; color: #fff; background: var(--muted); }
        .badge.pending { background: var(--busy); }
        .badge.paid { background: var(--reserved); }
        .badge.parked, .badge.done { background: var(--available); }
        .badge.expired { background: var(--occupied); }
        .tabs { display: flex; gap: 8px; margin: 16px 0; }
        .tabs a { text-decoration: none; padding: 8px 14px; border: 1px solid var(--line); border-radius: 999px; background: var(--card); }
        .tabs a[aria-current] { background: var(--ink); color: var(--bg); }
        .topnav { display: flex; justify-content: flex-end; margin-bottom: 8px; font-size: .9rem; }
        .back { display: inline-block; margin-bottom: 16px; }
        .small { font-size: .85rem; font-weight: 400; }
        .admin-box { max-width: 360px; margin: 48px auto; }
        .form { display: grid; gap: 12px; margin-top: 16px; }
        .form label { display: grid; gap: 4px; font-size: .9rem; }
        .form label.check { display: flex; gap: 8px; align-items: center; }
        .form input[type=email], .form input[type=password], .form input[type=text] { font: inherit; padding: 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--card); color: var(--ink); }
        .form button { font: inherit; font-weight: 600; padding: 10px; border: 0; border-radius: 8px; background: var(--available); color: #fff; cursor: pointer; }
        .error { color: var(--occupied); margin: 0; }
        .admin-bar { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; justify-content: flex-end; margin-bottom: 16px; font-size: .9rem; }
        .admin-bar form { margin: 0; }
        button.link { font: inherit; background: none; border: 0; padding: 0; color: inherit; text-decoration: underline; cursor: pointer; }
        .section-title { font-size: 1.1rem; margin: 32px 0 12px; }
        .table-wrap { overflow-x: auto; background: var(--card); border: 1px solid var(--line); border-radius: 12px; }
        .table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .table th, .table td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--line); white-space: nowrap; }
        .table tr:last-child td { border-bottom: 0; }
        .pager { display: flex; gap: 16px; justify-content: center; margin-top: 16px; }
    </style>
</head>
<body>
<main>
    @if (session('status'))
        <p class="flash ok" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="flash err" role="alert">{{ session('error') }}</p>
    @endif
    @yield('content')
</main>
<script src="/vendor/pusher-8.4.0.min.js"></script>
<script>
    // Koneksi WebSocket ke Laravel Reverb (protokol Pusher). Jika gagal,
    // halaman tetap memuat ulang data penuh secara berkala.
    window.parkirRealtime = function (channels) {
        const cfg = @json(config('parkir.realtime'));
        if (!cfg.key || !window.Pusher) return;
        const pusher = new Pusher(cfg.key, {
            wsHost: cfg.host, wsPort: cfg.port, wssPort: cfg.port,
            forceTLS: cfg.scheme === 'https', enabledTransports: ['ws', 'wss'], cluster: 'mt1',
        });
        for (const [name, events] of Object.entries(channels)) {
            const channel = pusher.subscribe(name);
            for (const [event, handler] of Object.entries(events)) channel.bind(event, handler);
        }
    };
</script>
@stack('scripts')
</body>
</html>
