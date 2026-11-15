@php($user = auth()->user())
<div class="admin-bar">
    @if ($user->hasRole('admin'))
        <a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>Status sensor</a>
    @endif
    @if ($user->hasRole('admin', 'petugas'))
        <a href="{{ route('admin.gate') }}" @if (request()->routeIs('admin.gate')) aria-current="page" @endif>Gerbang</a>
    @endif
    @if ($user->hasRole('admin', 'security'))
        <a href="{{ route('admin.security') }}" @if (request()->routeIs('admin.security')) aria-current="page" @endif>Laporan security</a>
    @endif
    @if ($user->hasRole('admin'))
        <a href="{{ route('admin.reports') }}" @if (request()->routeIs('admin.reports')) aria-current="page" @endif>Laporan</a>
    @endif
    <a href="{{ route('floors.index') }}">Halaman pengunjung</a>
    <span class="muted">{{ $user->email }} · {{ \App\Models\User::ROLE_LABELS[$user->role] ?? $user->role }}</span>
    <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button type="submit" class="link">Keluar</button>
    </form>
</div>
