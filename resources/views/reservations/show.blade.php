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
                <p>Bayar biaya admin sebelum <strong>{{ $wib($reservation->pay_before) }}</strong>. Slot ditahan untukmu sampai saat itu.</p>
                <form method="POST" action="{{ route('reservations.pay', $reservation) }}" class="form">
                    @csrf
                    <button type="submit">Bayar {{ \App\Models\Reservation::formatRupiah($reservation->fee) }}</button>
                </form>
                <p class="muted small">Pembayaran masih simulasi: belum ada uang yang ditarik.</p>
            @elseif ($status === \App\Models\Reservation::PAID)
                <p>Tunjukkan QR ini ke scanner di gerbang masuk sebelum <strong>{{ $wib($reservation->expires_at) }}</strong>. Lewat dari itu reservasi hangus.</p>
                <img class="qr" src="{{ route('reservations.qr', $reservation) }}" alt="QR reservasi {{ $reservation->code }}">
                <p class="qr-code">{{ $reservation->code }}</p>
            @elseif ($status === \App\Models\Reservation::PARKED)
                <p>Jam parkir berjalan sejak <strong>{{ $wib($reservation->checked_in_at) }}</strong>, sudah {{ \App\Models\Reservation::formatDuration($reservation->parkedMinutes()) }}. Scan QR yang sama di gerbang keluar.</p>
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
        // Status berubah saat petugas men-scan QR atau batas waktu lewat.
        setInterval(() => location.reload(), 30000);
    </script>
    @endpush
@endif
