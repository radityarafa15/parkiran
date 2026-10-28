@extends('layout')

@section('title', 'Gerbang '.$direction)

@section('content')
    @include('admin.nav')

    <h1>Gerbang {{ $direction }}</h1>
    <nav class="tabs">
        <a href="{{ route('admin.gate', ['arah' => 'masuk']) }}" @if ($direction === 'masuk') aria-current="page" @endif>Gerbang masuk</a>
        <a href="{{ route('admin.gate', ['arah' => 'keluar']) }}" @if ($direction === 'keluar') aria-current="page" @endif>Gerbang keluar</a>
    </nav>

    <form method="POST" action="{{ route('admin.gate.scan') }}" class="form card narrow" id="scan-form">
        @csrf
        <div class="camera" hidden>
            <video playsinline muted></video>
            <p class="muted small">Arahkan QR ke kamera.</p>
        </div>
        <button type="button" class="secondary" data-camera>Scan pakai kamera</button>
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

@push('scripts')
<script src="/vendor/jsqr-1.4.0.js"></script>
<script>
    // Scan QR lewat kamera (HP/laptop petugas). Browser hanya mengizinkan kamera
    // di https atau di localhost/127.0.0.1.
    (() => {
        const form = document.getElementById('scan-form');
        const input = form.querySelector('input[name=code]');
        const toggle = form.querySelector('[data-camera]');
        const box = form.querySelector('.camera');
        const video = box.querySelector('video');
        const note = box.querySelector('p');
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        let stream = null;

        if (!navigator.mediaDevices?.getUserMedia) {
            toggle.hidden = true;
            return;
        }

        function stop() {
            stream?.getTracks().forEach(t => t.stop());
            stream = null;
            box.hidden = true;
            toggle.textContent = 'Scan pakai kamera';
        }

        function scan() {
            if (!stream) return;
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.drawImage(video, 0, 0);
                const found = jsQR(ctx.getImageData(0, 0, canvas.width, canvas.height).data, canvas.width, canvas.height);
                if (found && /^RSV/i.test(found.data.trim())) {
                    input.value = found.data.trim();
                    stop();
                    form.submit();
                    return;
                }
            }
            requestAnimationFrame(scan);
        }

        toggle.addEventListener('click', async () => {
            if (stream) return stop();
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            } catch (e) {
                box.hidden = false;
                note.textContent = 'Kamera tidak bisa dibuka. Izinkan akses kamera, atau buka halaman ini lewat https / localhost.';
                return;
            }
            video.srcObject = stream;
            await video.play();
            box.hidden = false;
            note.textContent = 'Arahkan QR ke kamera.';
            toggle.textContent = 'Tutup kamera';
            requestAnimationFrame(scan);
        });
    })();
</script>
@endpush
