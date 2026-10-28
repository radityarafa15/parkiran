@extends('layout')

@section('title', 'Reservasi saya')

@section('content')
    <a class="back" href="{{ route('floors.index') }}">&larr; Semua lantai</a>
    <h1>Reservasi saya</h1>
    <p class="muted">Reservasi yang dibuat dari browser ini.</p>

    @forelse ($reservations as $reservation)
        <a class="card" style="display:block;text-decoration:none" href="{{ route('reservations.show', $reservation) }}">
            <strong>{{ $reservation->slot->code }}</strong> · {{ $reservation->slot->floor->name }} · {{ $reservation->plate_number }}
            <span class="badge {{ $reservation->currentStatus() }}">{{ $reservation->statusLabel() }}</span>
        </a>
    @empty
        <div class="card">Belum ada reservasi. Pilih lantai, lalu ketuk slot hijau untuk reservasi.</div>
    @endforelse
@endsection
