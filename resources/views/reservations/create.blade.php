@extends('layout')

@section('title', 'Reservasi '.$slot->code)

@section('content')
    <a class="back" href="{{ route('floors.show', $slot->floor) }}">&larr; {{ $slot->floor->name }}</a>
    <div class="narrow">
        <h1>Reservasi slot {{ $slot->code }}</h1>
        <p class="muted">{{ $slot->floor->name }} · Zona {{ $slot->zone }}</p>

        <div class="card">
            <dl class="facts">
                <dt>Biaya admin</dt><dd>{{ \App\Models\Reservation::formatRupiah($config['fee']) }}</dd>
                <dt>Bayar dalam</dt><dd>{{ $config['payment_minutes'] }} menit</dd>
                <dt>Berlaku</dt><dd>{{ $config['valid_minutes'] / 60 }} jam setelah dibayar</dd>
            </dl>
            <p class="muted small">Setelah dibayar kamu mendapat QR. Scan QR di gerbang masuk; jam parkir mulai dihitung saat itu. Jika belum masuk dalam {{ $config['valid_minutes'] / 60 }} jam, reservasi hangus dan biaya admin tidak dikembalikan.</p>

            <form method="POST" action="{{ route('reservations.store') }}" class="form">
                @csrf
                <input type="hidden" name="slot_id" value="{{ $slot->id }}">
                <label>Nama
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="60" autocomplete="name">
                </label>
                @error('name')<p class="error">{{ $message }}</p>@enderror
                <label>Plat nomor
                    <input type="text" name="plate_number" value="{{ old('plate_number') }}" required maxlength="16" placeholder="B 1234 XYZ" autocapitalize="characters">
                </label>
                @error('plate_number')<p class="error">{{ $message }}</p>@enderror
                <button type="submit">Reservasi</button>
            </form>
        </div>
    </div>
@endsection
