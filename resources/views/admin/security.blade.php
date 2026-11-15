@extends('layout')

@section('title', 'Laporan security')

@php($ago = fn ($time) => $time->locale('id')->diffForHumans())

@section('content')
    @include('admin.nav')

    <h1>Laporan masuk <span class="muted small">({{ $open->count() }} belum selesai)</span></h1>
    <p class="muted small">Laporan dari pengunjung yang slot reservasinya bermasalah. Halaman ini memuat ulang tiap 20 detik.</p>

    @if ($open->isEmpty())
        <p class="card">Tidak ada laporan yang perlu ditangani.</p>
    @else
        <div class="report-list">
            @foreach ($open as $report)
                <div class="card">
                    <div class="report-head">
                        <strong>{{ $report->typeLabel() }}</strong>
                        <span class="badge {{ $report->status }}">{{ $report->statusLabel() }}</span>
                    </div>
                    <p class="muted small">Slot {{ $report->slot->code }} · {{ $report->slot->floor->name }} · Zona {{ $report->slot->zone }} · {{ $ago($report->created_at) }}</p>
                    @if ($report->note)
                        <p>Catatan pelapor: {{ $report->note }}</p>
                    @endif
                    <p class="small">Pelapor: {{ $report->reservation->plate_number }} ({{ $report->reservation->name }}) · kode {{ $report->reservation->code }}</p>
                    @if ($report->replacementSlot)
                        <p class="small">Slot pengganti yang sudah ditahan: <strong>{{ $report->replacementSlot->code }}</strong></p>
                    @elseif ($report->type === \App\Models\SlotReport::OCCUPIED)
                        <p class="small">Tidak ada slot kosong di lantai ini untuk pengganti. Arahkan pengunjung ke lantai lain.</p>
                    @endif
                    @if ($report->handler)
                        <p class="muted small">Ditangani {{ $report->handler->name }} sejak {{ $report->handled_at->timezone('Asia/Jakarta')->format('H:i') }} WIB</p>
                    @endif
                    <div class="report-actions form">
                        @if ($report->status === \App\Models\SlotReport::NEW)
                            <form method="POST" action="{{ route('admin.security.handle', $report) }}">
                                @csrf
                                <button type="submit">Tangani</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.security.resolve', $report) }}">
                            @csrf
                            <button type="submit" class="secondary">Tandai selesai</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <h2 class="section-title">Baru selesai</h2>
    @if ($done->isEmpty())
        <p class="muted">Belum ada.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Selesai</th><th>Slot</th><th>Masalah</th><th>Pelapor</th><th>Ditangani</th></tr></thead>
                <tbody>
                    @foreach ($done as $report)
                        <tr>
                            <td>{{ $report->resolved_at->timezone('Asia/Jakarta')->format('d/m H:i') }}</td>
                            <td>{{ $report->slot->code }}{{ $report->replacementSlot ? ' → '.$report->replacementSlot->code : '' }}</td>
                            <td>{{ $report->typeLabel() }}</td>
                            <td>{{ $report->reservation->plate_number }}</td>
                            <td>{{ $report->handler?->name ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

@push('scripts')
<script>setTimeout(() => location.reload(), 20000);</script>
@endpush
