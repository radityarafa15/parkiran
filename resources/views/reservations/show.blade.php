@extends('layout')

@section('title', 'Reservasi '.$reservation->code)

@php
    $status = $reservation->currentStatus();
    $wib = fn ($time) => $time->timezone('Asia/Jakarta')->format('d/m H:i').' WIB';
@endphp

@section('content')
    <nav class="topnav"><a href="{{ route('reservations.index') }}">Reservasi saya</a></nav>
    <div class="narrow">
        <h1>Reservasi {{ $reservation->slot->code }}</h1>
        <p><span class="badge {{ $status }}">{{ $reservation->statusLabel() }}</span></p>

        <div class="card">
            @if ($status === \App\Models\Reservation::PENDING)
                <p class="countdown">Sisa waktu bayar <strong data-countdown="{{ $reservation->pay_before->toIso8601String() }}">{{ \App\Models\Reservation::formatDuration((int) now()->diffInMinutes($reservation->pay_before)) }}</strong></p>
                <p>Bayar biaya admin sebelum <strong>{{ $wib($reservation->pay_before) }}</strong>. Slot ditahan untukmu sampai saat itu.</p>
                <form method="POST" action="{{ route('reservations.pay', $reservation) }}" class="form">
                    @csrf
                    <button type="submit">Bayar {{ \App\Models\Reservation::formatRupiah($reservation->fee) }}</button>
                </form>
                <p class="muted small">Pembayaran masih simulasi: belum ada uang yang ditarik.</p>
            @elseif ($status === \App\Models\Reservation::PAID)
                <p class="countdown">Sisa waktu masuk <strong data-countdown="{{ $reservation->expires_at->toIso8601String() }}">{{ \App\Models\Reservation::formatDuration((int) now()->diffInMinutes($reservation->expires_at)) }}</strong></p>
                <p>Tunjukkan QR ini ke scanner di gerbang masuk sebelum <strong>{{ $wib($reservation->expires_at) }}</strong>. Lewat dari itu reservasi hangus.</p>
                <img class="qr" src="{{ route('reservations.qr', $reservation) }}" alt="QR reservasi {{ $reservation->code }}">
                <p class="qr-code">{{ $reservation->code }}</p>
            @elseif ($status === \App\Models\Reservation::PARKED)
                <p class="countdown">Lama parkir <strong data-elapsed="{{ $reservation->checked_in_at->toIso8601String() }}">{{ \App\Models\Reservation::formatDuration($reservation->parkedMinutes()) }}</strong></p>
                <p>Jam parkir berjalan sejak <strong>{{ $wib($reservation->checked_in_at) }}</strong>. Scan QR yang sama di gerbang keluar.</p>
                <img class="qr" src="{{ route('reservations.qr', $reservation) }}" alt="QR reservasi {{ $reservation->code }}">
                <p class="qr-code">{{ $reservation->code }}</p>
            @elseif ($status === \App\Models\Reservation::DONE)
                <p>Parkir {{ \App\Models\Reservation::formatDuration($reservation->parkedMinutes()) }} ({{ $reservation->parkedHours() }} jam dihitung). Terima kasih.</p>
            @else
                <p>{{ $reservation->paid_at ? 'QR tidak di-scan di gerbang masuk sebelum batas waktunya, jadi reservasi hangus dan biaya admin tidak dikembalikan.' : 'Biaya admin tidak dibayar tepat waktu, jadi slotnya dilepas.' }}</p>
                <p><a href="{{ route('floors.show', $reservation->slot->floor) }}">Pilih slot lain</a></p>
            @endif
        </div>

        <div class="card">
            <dl class="facts">
                <dt>Slot</dt><dd>{{ $reservation->slot->code }} · {{ $reservation->slot->floor->name }}</dd>
                <dt>Nama</dt><dd>{{ $reservation->name }}</dd>
                <dt>Plat nomor</dt><dd>{{ $reservation->plate_number }}</dd>
                <dt>Biaya admin</dt><dd>{{ \App\Models\Reservation::formatRupiah($reservation->fee) }}{{ $reservation->paid_at ? ' · lunas' : '' }}</dd>
                <dt>Kode</dt><dd>{{ $reservation->code }}</dd>
            </dl>
        </div>
        <p class="muted small">Simpan alamat halaman ini; siapa pun yang punya alamat atau QR ini bisa memakai reservasimu.</p>
    </div>
@endsection

@if (in_array($status, [\App\Models\Reservation::PENDING, \App\Models\Reservation::PAID, \App\Models\Reservation::PARKED]))
    @push('scripts')
    <script>
        // Jam berjalan di browser; waktu server dipakai sebagai acuan supaya
        // jam HP yang meleset tidak membuat hitungan salah.
        const skew = Date.parse(@json(now()->toIso8601String())) - Date.now();
        const pad = n => String(n).padStart(2, '0');
        const clock = ms => {
            const s = Math.max(0, Math.floor(ms / 1000));
            return `${Math.floor(s / 3600)}:${pad(Math.floor(s / 60) % 60)}:${pad(s % 60)}`;
        };

        function tick() {
            const now = Date.now() + skew;
            for (const el of document.querySelectorAll('[data-countdown]')) {
                const left = Date.parse(el.dataset.countdown) - now;
                el.textContent = clock(left);
                el.closest('.countdown').dataset.urgent = left < 10 * 60000 ? '1' : '0';
                if (left <= 0) location.reload();
            }
            for (const el of document.querySelectorAll('[data-elapsed]')) {
                el.textContent = clock(now - Date.parse(el.dataset.elapsed));
            }
        }
        tick();
        setInterval(tick, 1000);
        // Status berubah saat petugas men-scan QR.
        setInterval(() => location.reload(), 30000);
    </script>
    @endpush
@endif
