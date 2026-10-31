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
        <span><span class="dot s-reserved"></span>Direservasi</span>
        <span><span class="dot s-unknown"></span>Sensor tidak melapor</span>
    </div>
    <p class="muted small">Ketuk slot hijau untuk reservasi (biaya admin {{ \App\Models\Reservation::formatRupiah(config('parkir.reservation.fee')) }}).</p>

    <h2 class="section-title">Denah lantai</h2>
    <p class="muted small">Tiap zona adalah satu blok seperti denah asli lantai ini. Ketuk zona untuk melihat slotnya; warnanya menunjukkan sisa slot kosong.</p>
    <nav class="zone-map" aria-label="Daftar zona">
        @foreach ($zones as $zone)
            <a class="zone-tile" href="#zona-{{ $zone['zone'] }}" data-zone-chip="{{ $zone['zone'] }}" data-total="{{ $zone['total'] }}"
               data-level="{{ $zone['available'] === 0 ? 'full' : ($zone['available'] <= $zone['total'] * 0.1 ? 'busy' : 'ok') }}">
                <strong>{{ $zone['zone'] }}</strong>
                <span><span data-zone-available>{{ $zone['available'] }}</span> kosong</span>
            </a>
        @endforeach
    </nav>

    @foreach ($slotsByZone as $zone => $slots)
        <section class="zone" id="zona-{{ $zone }}">
            <h2>Zona {{ $zone }}</h2>
            <div class="plan-scroll">
                <div class="plan" style="--columns: {{ config('parkir.map.columns') }}">
                    @foreach (\App\Support\FloorPlan::rows($slots) as $row)
                        @if (! $loop->first)
                            <div class="plan-aisle" aria-hidden="true">jalur</div>
                        @endif
                        @foreach ($row as $cell)
                            @if ($cell['type'] === 'bay')
                                <div class="bay">
                                    @foreach ($cell['slots'] as $slot)
                                        @if ($slot)
                                            <a class="slot s-{{ $slot->status() }}" href="{{ route('reservations.create', ['slot' => $slot->id]) }}" data-slot="{{ $slot->id }}" data-zone="{{ $zone }}" title="{{ $slot->code }}">{{ \Illuminate\Support\Str::after($slot->code, '-'.$zone) }}</a>
                                        @else
                                            <span class="slot s-none" aria-hidden="true"></span>
                                        @endif
                                    @endforeach
                                </div>
                            @elseif ($cell['type'] === 'core')
                                <div class="core">CORE<br><span class="small">lift/tangga</span></div>
                            @else
                                <div aria-hidden="true"></div>
                            @endif
                        @endforeach
                    @endforeach
                </div>
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
            chip.dataset.level = free === 0 ? 'full' : (free <= chip.dataset.total * 0.1 ? 'busy' : 'ok');
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
