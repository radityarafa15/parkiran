@extends('layout')

@section('title', $floor->name)

@section('content')
    <a class="back" href="{{ route('floors.index') }}">&larr; Semua lantai</a>
    @php($s = $floor->summary())
    <h1>{{ $floor->name }}</h1>
    <p class="muted"><span id="available">{{ $s['available'] }}</span> dari <span id="total">{{ $s['total'] }}</span> slot kosong</p>

    <div class="legend">
        <span><span class="dot s-available"></span>Kosong</span>
        <span><span class="dot s-occupied"></span>Terisi</span>
        <span><span class="dot s-unknown"></span>Sensor tidak melapor</span>
    </div>

    <div class="slots">
        @foreach ($floor->slots as $slot)
            <div class="slot s-{{ $slot->status() }}" data-slot="{{ $slot->id }}" title="{{ $slot->code }}">{{ $slot->code }}</div>
        @endforeach
    </div>
@endsection

@push('scripts')
<script>
    async function refresh() {
        try {
            const res = await fetch('/api/floors/{{ $floor->id }}', { headers: { Accept: 'application/json' } });
            const { data } = await res.json();
            document.getElementById('available').textContent = data.available;
            document.getElementById('total').textContent = data.total;
            for (const slot of data.slots) {
                const el = document.querySelector(`[data-slot="${slot.id}"]`);
                if (el) el.className = `slot s-${slot.status}`;
            }
        } catch (e) { /* coba lagi di putaran berikutnya */ }
    }
    setInterval(refresh, 5000);
</script>
@endpush
