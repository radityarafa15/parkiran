@extends('layout')

@section('title', 'Ketersediaan parkir')

@section('content')
    <h1>Ketersediaan parkir</h1>
    <p class="muted">Pilih lantai untuk melihat slot yang masih kosong. Diperbarui otomatis.</p>

    <div class="floors">
        @foreach ($floors as $floor)
            @php($s = $floor->summary())
            <a class="floor-card" href="{{ route('floors.show', $floor) }}" data-floor="{{ $floor->id }}"
               data-level="{{ $s['available'] === 0 ? 'full' : ($s['available'] <= $s['total'] * 0.1 ? 'busy' : 'ok') }}">
                <h2>{{ $floor->name }}</h2>
                <div><span class="big" data-field="available">{{ $s['available'] }}</span> <span class="muted">slot kosong</span></div>
                <div class="muted"><span data-field="occupied">{{ $s['occupied'] }}</span> terisi · <span data-field="total">{{ $s['total'] }}</span> total</div>
            </a>
        @endforeach
    </div>
@endsection

@push('scripts')
<script>
    async function refresh() {
        try {
            const res = await fetch('/api/floors', { headers: { Accept: 'application/json' } });
            const { data } = await res.json();
            for (const f of data) {
                const card = document.querySelector(`[data-floor="${f.id}"]`);
                if (!card) continue;
                for (const key of ['available', 'occupied', 'total']) {
                    card.querySelector(`[data-field="${key}"]`).textContent = f[key];
                }
                card.dataset.level = f.available === 0 ? 'full' : (f.available <= f.total * 0.1 ? 'busy' : 'ok');
            }
        } catch (e) { /* coba lagi di putaran berikutnya */ }
    }
    setInterval(refresh, 5000);
</script>
@endpush
