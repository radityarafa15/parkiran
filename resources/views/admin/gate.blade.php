@extends('layout')

@section('title', 'Gerbang '.$direction)

@section('content')
    <div class="admin-bar">
        <a href="{{ route('admin.dashboard') }}">Status sensor</a>
        <span class="muted">{{ auth()->user()->email }}</span>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="link">Keluar</button>
        </form>
    </div>

    <h1>Gerbang {{ $direction }}</h1>
    <nav class="tabs">
        <a href="{{ route('admin.gate', ['arah' => 'masuk']) }}" @if ($direction === 'masuk') aria-current="page" @endif>Gerbang masuk</a>
        <a href="{{ route('admin.gate', ['arah' => 'keluar']) }}" @if ($direction === 'keluar') aria-current="page" @endif>Gerbang keluar</a>
    </nav>

    <form method="POST" action="{{ route('admin.gate.scan') }}" class="form card narrow">
        @csrf
        <input type="hidden" name="arah" value="{{ $direction }}">
        <label>Scan QR atau ketik kode reservasi
            <input type="text" name="code" required autofocus autocomplete="off" placeholder="RSV…" autocapitalize="characters">
        </label>
        <button type="submit">{{ $direction === 'masuk' ? 'Catat masuk' : 'Catat keluar' }}</button>
    </form>

    <h2 class="section-title">
        {{ $direction === 'masuk' ? 'Menunggu masuk' : 'Sedang parkir' }}
        <span class="muted">({{ $waiting->count() }})</span>
    </h2>

    @if ($waiting->isEmpty())
        <p class="muted">Tidak ada.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Kode</th><th>Plat</th><th>Slot</th><th>{{ $direction === 'masuk' ? 'Hangus pada' : 'Masuk sejak' }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($waiting as $reservation)
                        <tr>
                            <td><code>{{ $reservation->code }}</code></td>
                            <td>{{ $reservation->plate_number }}</td>
                            <td>{{ $reservation->slot->code }} · {{ $reservation->slot->floor->name }}</td>
                            <td>{{ ($direction === 'masuk' ? $reservation->expires_at : $reservation->checked_in_at)->timezone('Asia/Jakarta')->format('d/m H:i') }} WIB</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
