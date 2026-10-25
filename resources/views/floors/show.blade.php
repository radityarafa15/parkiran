@extends('layout')

@section('title', $floor->name)

@section('content')
    <a class="back" href="{{ route('floors.index') }}">&larr; Semua lantai</a>
    @php($s = $floor->summary())
    <h1>{{ $floor->name }}</h1>
    <p class="muted"><span id="available">{{ number_format($s['available'], 0, ',', '.') }}</span> dari <span id="total">{{ number_format($s['total'], 0, ',', '.') }}</span> slot kosong</p>

    <div class="legend">
        <span><span class="dot s-available"></span>Kosong</span>
        <span><span class="dot s-occupied"></span>Terisi</span>
        <span><span class="dot s-unknown"></span>Sensor tidak melapor</span>
    </div>

    <nav class="zones">
        @foreach ($zones as $zone)
            <a class="zone-chip" href="#zona-{{ $zone['zone'] }}" data-zone-chip="{{ $zone['zone'] }}" data-full="{{ $zone['available'] === 0 ? 1 : 0 }}">
                Zona {{ $zone['zone'] }} · <strong data-zone-available>{{ $zone['available'] }}</strong> kosong
            </a>
        @endforeach
    </nav>

    @foreach ($slotsByZone as $zone => $slots)
        <section class="zone" id="zona-{{ $zone }}">
            <h2>Zona {{ $zone }}</h2>
            <div class="slots">
                @foreach ($slots as $slot)
                    <div class="slot s-{{ $slot->status() }}" data-slot="{{ $slot->id }}" data-zone="{{ $zone }}" title="{{ $slot->code }}">{{ \Illuminate\Support\Str::after($slot->code, '-') }}</div>
                @endforeach
            </div>
        </section>
    @endforeach
@endsection

@push('scripts')
<script>
    const fmt = n => n.toLocaleString('id-ID');

    function renderSummary(f) {
        document.getElementById('available').textContent = fmt(f.available);
        document.getElementById('total').textContent = fmt(f.total);
    }

    function renderSlots(slots) {
        for (const slot of slots) {
            const el = document.querySelector(`[data-slot="${slot.id}"]`);
            if (el) el.className = `slot s-${slot.status}`;
        }
        // Hitung ulang angka per zona dari grid yang sudah diperbarui.
        for (const chip of document.querySelectorAll('[data-zone-chip]')) {
            const zone = chip.dataset.zoneChip;
            const free = document.querySelectorAll(`[data-zone="${zone}"].s-available`).length;
            chip.querySelector('[data-zone-available]').textContent = free;
            chip.dataset.full = free === 0 ? '1' : '0';
        }
    }

    async function refresh() {
        try {
            const opts = { headers: { Accept: 'application/json' } };
            const [floor, slots] = await Promise.all([
                fetch('/api/floors/{{ $floor->id }}', opts).then(r => r.json()),
                fetch('/api/floors/{{ $floor->id }}/slots', opts).then(r => r.json()),
            ]);
            renderSummary(floor.data);
            renderSlots(slots.data);
        } catch (e) { /* coba lagi di putaran berikutnya */ }
    }

    parkirRealtime({
        'floor.{{ $floor->id }}': {
            'slots.changed': e => renderSlots(e.slots),
            'floor.summary': renderSummary,
        },
    });
    setInterval(refresh, 60000);
</script>
@endpush
