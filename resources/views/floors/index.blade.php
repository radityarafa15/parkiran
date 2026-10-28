@extends('layout')

@section('title', 'Ketersediaan parkir')

@section('content')
    <nav class="topnav"><a href="{{ route('reservations.index') }}">Reservasi saya</a></nav>
    <h1>Ketersediaan parkir</h1>
    <p class="muted">Pilih lantai untuk melihat slot yang masih kosong. Diperbarui langsung.</p>

    <div class="floors">
        @foreach ($floors as $floor)
            @php($s = $floor->summary())
            <a class="floor-card" href="{{ route('floors.show', $floor) }}" data-floor="{{ $floor->id }}"
               data-level="{{ $s['available'] === 0 ? 'full' : ($s['available'] <= $s['total'] * 0.1 ? 'busy' : 'ok') }}">
                <h2>{{ $floor->name }}</h2>
                <div><span class="big" data-field="available">{{ number_format($s['available'], 0, ',', '.') }}</span> <span class="muted">slot kosong</span></div>
                <div class="muted"><span data-field="occupied">{{ number_format($s['occupied'], 0, ',', '.') }}</span> terisi · <span data-field="total">{{ number_format($s['total'], 0, ',', '.') }}</span> total</div>
            </a>
        @endforeach
    </div>
@endsection

@push('scripts')
<script>
    const fmt = n => n.toLocaleString('id-ID');

    function render(f) {
        const card = document.querySelector(`[data-floor="${f.id}"]`);
        if (!card) return;
        for (const key of ['available', 'occupied', 'total']) {
            card.querySelector(`[data-field="${key}"]`).textContent = fmt(f[key]);
        }
        card.dataset.level = f.available === 0 ? 'full' : (f.available <= f.total * 0.1 ? 'busy' : 'ok');
    }

    async function refresh() {
        try {
            const res = await fetch('/api/floors', { headers: { Accept: 'application/json' } });
            (await res.json()).data.forEach(render);
        } catch (e) { /* coba lagi di putaran berikutnya */ }
    }

    parkirRealtime({ floors: { 'floor.summary': render } });
    setInterval(refresh, 60000);
</script>
@endpush
