@extends('layout')

@section('title', 'Laporan reservasi')

@php($rp = fn ($n) => \App\Models\Reservation::formatRupiah($n))

@section('content')
    @include('admin.nav')

    <h1>Laporan reservasi</h1>
    <nav class="tabs">
        @foreach ($ranges as $value => $label)
            <a href="{{ route('admin.reports', ['hari' => $value]) }}" @if ($days === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="stats">
        <div class="card stat"><span class="muted">Pendapatan biaya admin</span><span class="big">{{ $rp($stats['revenue']) }}</span><span class="muted small">dari {{ $stats['paid'] }} reservasi dibayar</span></div>
        <div class="card stat"><span class="muted">Reservasi dibuat</span><span class="big">{{ $stats['created'] }}</span><span class="muted small">{{ $stats['unpaid'] }} batal karena tidak dibayar</span></div>
        <div class="card stat"><span class="muted">Hangus (sudah bayar, tidak masuk)</span><span class="big">{{ $stats['forfeited'] }}</span><span class="muted small">{{ $stats['paid'] ? round($stats['forfeited'] / $stats['paid'] * 100) : 0 }}% dari yang dibayar</span></div>
        <div class="card stat"><span class="muted">Sedang parkir sekarang</span><span class="big">{{ $stats['parked'] }}</span><span class="muted small">{{ $stats['waiting'] }} menunggu masuk</span></div>
        <div class="card stat"><span class="muted">Selesai</span><span class="big">{{ $stats['done'] }}</span><span class="muted small">rata-rata parkir {{ $stats['avg_minutes'] === null ? '-' : \App\Models\Reservation::formatDuration($stats['avg_minutes']) }}</span></div>
    </div>

    @if ($days > 1)
        <h2 class="section-title">Per hari</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Dibuat</th><th>Dibayar</th><th>Hangus</th><th>Pendapatan</th></tr></thead>
                <tbody>
                    @foreach ($perDay as $row)
                        <tr>
                            <td>{{ $row['date']->locale('id')->translatedFormat('D, d M') }}</td>
                            <td>{{ $row['created'] }}</td>
                            <td>{{ $row['paid'] }}</td>
                            <td>{{ $row['expired'] }}</td>
                            <td>{{ $rp($row['revenue']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 class="section-title">Reservasi terbaru <span class="muted">({{ $recent->count() }}{{ $stats['created'] > $recent->count() ? ' dari '.$stats['created'] : '' }})</span></h2>
    @if ($recent->isEmpty())
        <p class="muted">Belum ada reservasi pada periode ini.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Dibuat</th><th>Kode</th><th>Plat</th><th>Slot</th><th>Status</th><th>Lama parkir</th></tr></thead>
                <tbody>
                    @foreach ($recent as $reservation)
                        <tr>
                            <td>{{ $reservation->created_at->timezone('Asia/Jakarta')->format('d/m H:i') }}</td>
                            <td><code>{{ $reservation->code }}</code></td>
                            <td>{{ $reservation->plate_number }}</td>
                            <td>{{ $reservation->slot->code }}</td>
                            <td><span class="badge {{ $reservation->currentStatus() }}">{{ $reservation->statusLabel() }}</span></td>
                            <td>{{ $reservation->parkedMinutes() === null ? '-' : \App\Models\Reservation::formatDuration($reservation->parkedMinutes()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
