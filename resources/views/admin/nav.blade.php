<div class="admin-bar">
    <a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>Status sensor</a>
    <a href="{{ route('admin.gate') }}" @if (request()->routeIs('admin.gate')) aria-current="page" @endif>Gerbang</a>
    <a href="{{ route('admin.reports') }}" @if (request()->routeIs('admin.reports')) aria-current="page" @endif>Laporan</a>
    <a href="{{ route('floors.index') }}">Halaman pengunjung</a>
    <span class="muted">{{ auth()->user()->email }}</span>
    <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button type="submit" class="link">Keluar</button>
    </form>
</div>
