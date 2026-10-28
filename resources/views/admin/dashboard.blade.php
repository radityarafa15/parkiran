@extends('layout')

@section('title', 'Status sensor')

@section('content')
    <div class="admin-bar">
        <a href="{{ route('floors.index') }}">Halaman pengunjung</a>
        <a href="{{ route('admin.gate') }}">Gerbang</a>
        <span class="muted">{{ auth()->user()->email }}</span>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="link">Keluar</button>
        </form>
    </div>

    <h1>Status sensor</h1>
    <p class="muted">Sensor dianggap offline jika tidak melapor lebih dari {{ $staleMinutes }} menit. Slotnya tampil abu-abu di aplikasi pengunjung.</p>

    <div class="floors">
        @foreach ($floors as $floor)
            @php($s = $floor->summary())
            <a class="floor-card" href="{{ route('admin.dashboard', ['floor' => $floor->id]) }}"
               data-level="{{ $s['unknown'] === 0 ? 'ok' : ($s['unknown'] <= $s['total'] * 0.02 ? 'busy' : 'full') }}">
                <h2>{{ $floor->name }}</h2>
                <div><span class="big">{{ number_format($s['unknown'], 0, ',', '.') }}</span> <span class="muted">sensor offline</span></div>
                <div class="muted">dari {{ number_format($s['total'], 0, ',', '.') }} sensor</div>
            </a>
        @endforeach
    </div>

    <h2 class="section-title">
        Sensor offline{{ $selectedFloor ? ' di '.$floors->firstWhere('id', $selectedFloor)?->name : '' }}
        <span class="muted">({{ number_format($offline->total(), 0, ',', '.') }})</span>
        @if ($selectedFloor)
            <a class="small" href="{{ route('admin.dashboard') }}">tampilkan semua lantai</a>
        @endif
    </h2>

    @if ($offline->isEmpty())
        <p>Semua sensor melapor dengan normal.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Slot</th><th>ID sensor</th><th>Lantai</th><th>Laporan terakhir</th><th>Jarak terakhir</th></tr>
                </thead>
                <tbody>
                    @foreach ($offline as $slot)
                        <tr>
                            <td><strong>{{ $slot->code }}</strong></td>
                            <td><code>{{ $slot->sensor_id }}</code></td>
                            <td>{{ $slot->floor->name }}</td>
                            <td>
                                @if ($slot->last_seen_at)
                                    {{ $slot->last_seen_at->locale('id')->diffForHumans() }}
                                    <span class="muted small">{{ $slot->last_seen_at->timezone('Asia/Jakarta')->format('d/m H:i') }} WIB</span>
                                @else
                                    <span class="muted">belum pernah</span>
                                @endif
                            </td>
                            <td>{{ $slot->distance_cm !== null ? $slot->distance_cm.' cm' : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($offline->hasPages())
            <nav class="pager">
                @if ($offline->previousPageUrl())
                    <a href="{{ $offline->previousPageUrl() }}">&larr; Sebelumnya</a>
                @endif
                <span class="muted">Halaman {{ $offline->currentPage() }} dari {{ $offline->lastPage() }}</span>
                @if ($offline->nextPageUrl())
                    <a href="{{ $offline->nextPageUrl() }}">Berikutnya &rarr;</a>
                @endif
            </nav>
        @endif
    @endif
@endsection
