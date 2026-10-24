# Parkiran

Aplikasi untuk melihat lantai dan slot parkir mall yang masih kosong. Status slot berasal dari sensor ultrasonik di atas tiap slot.

## Cara kerja

1. Gateway di tiap lantai mengirim pembacaan sensor ke `POST /api/sensor-readings`.
2. Jarak di bawah `PARKIR_OCCUPIED_THRESHOLD_CM` (default 150 cm) berarti ada mobil, jadi slot terisi.
3. Sensor yang tidak melapor lebih dari `PARKIR_STALE_AFTER_SECONDS` (default 300 detik) ditampilkan "tidak diketahui", bukan "kosong".
4. Pengunjung membuka halaman daftar lantai, lalu memilih lantai untuk melihat tiap slot. Halaman memperbarui diri tiap 5 detik.

## Menjalankan secara lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
# isi PARKIR_SENSOR_TOKEN di .env
touch database/database.sqlite
php artisan migrate --seed      # 3 lantai x 40 slot (perkiraan, sesuaikan dengan denah)
php artisan serve
php artisan parkir:simulate     # di terminal lain, mensimulasikan sensor
php artisan test
```

## API

| Metode | Path | Keterangan |
| --- | --- | --- |
| GET | `/api/floors` | Ringkasan tiap lantai: total, kosong, terisi, tidak diketahui |
| GET | `/api/floors/{id}` | Ringkasan lantai beserta status tiap slot |
| POST | `/api/sensor-readings` | Untuk gateway sensor, wajib header `X-Sensor-Token` |

Contoh kiriman gateway:

```json
{
  "readings": [
    { "sensor_id": "US-P1-A01", "distance_cm": 82, "measured_at": "2026-10-24T08:50:00+07:00" },
    { "sensor_id": "US-P1-A02", "distance_cm": 255 }
  ]
}
```

Balasan berisi jumlah pembacaan yang diterima dan daftar `unknown_sensors` (ID sensor yang belum terdaftar ke slot mana pun).

## Belum dikerjakan

- Push realtime (Laravel Reverb) sebagai pengganti polling 5 detik.
- Aplikasi mobile yang memakai API di atas.
- Dashboard admin untuk sensor yang offline.
- Penyesuaian data slot dan ID sensor dengan instalasi asli di mall.
