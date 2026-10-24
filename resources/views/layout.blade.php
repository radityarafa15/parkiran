<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Parkiran') · {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #f6f7f9; --card: #fff; --ink: #1d232b; --muted: #5f6b7a; --line: #dfe3e8;
            --available: #1f9d55; --occupied: #d64545; --unknown: #9aa5b1; --busy: #d9822b;
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
        .slots { display: grid; gap: 8px; grid-template-columns: repeat(auto-fill, minmax(72px, 1fr)); }
        .slot { border-radius: 8px; padding: 14px 4px; text-align: center; font-weight: 600; font-size: .85rem; color: #fff; }
        .s-available { background: var(--available); }
        .s-occupied { background: var(--occupied); }
        .s-unknown { background: var(--unknown); }
        .back { display: inline-block; margin-bottom: 16px; }
    </style>
</head>
<body>
<main>
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
