# Parkiran

Aplikasi untuk melihat lantai dan slot parkir mall yang masih kosong, untuk web dan mobile. Status slot berasal dari sensor ultrasonik di atas tiap slot. Data awal: 3 lantai, 6.995 slot mobil, dibagi ke zona berisi 100 slot.

- Backend + web: Laravel (folder ini)
- Realtime: Laravel Reverb (WebSocket, protokol Pusher)
- Mobile: Flutter, di folder [`mobile/`](mobile/README.md)

## Cara kerja

1. Gateway di tiap lantai mengirim pembacaan sensor ke `POST /api/sensor-readings`, maksimal 500 pembacaan per permintaan.
2. Jarak di bawah `PARKIR_OCCUPIED_THRESHOLD_CM` (default 150 cm) berarti ada mobil, jadi slot terisi.
3. Hanya slot yang statusnya berubah yang disiarkan lewat Reverb, beserta ringkasan lantainya.
4. Sensor yang tidak melapor lebih dari `PARKIR_STALE_AFTER_SECONDS` (default 300 detik) ditampilkan "tidak diketahui", bukan "kosong". Perintah terjadwal `parkir:broadcast-summaries` menyiarkan ulang ringkasan tiap menit untuk menangkap perubahan ini.
5. Web dan mobile: daftar lantai, lalu pilih lantai untuk melihat zona dan tiap slot. Keduanya juga memuat ulang data penuh tiap 60 detik sebagai cadangan.

## Menjalankan secara lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
# isi PARKIR_SENSOR_TOKEN, REVERB_APP_KEY, REVERB_APP_SECRET di .env
touch database/database.sqlite
php artisan migrate --seed      # 3 lantai, 6.995 slot (pembagian per lantai di config/parkir.php)

php artisan serve               # web + API
php artisan reverb:start        # WebSocket realtime
php artisan schedule:work       # siaran ulang ringkasan tiap menit
php artisan parkir:simulate     # simulator sensor

php artisan test
```

## API

| Metode | Path | Keterangan |
| --- | --- | --- |
| GET | `/api/floors` | Ringkasan tiap lantai: total, kosong, terisi, tidak diketahui |
| GET | `/api/floors/{id}` | Ringkasan lantai dan per zona |
| GET | `/api/floors/{id}/slots?zone=A` | Status slot di lantai, bisa disaring per zona |
| GET | `/api/realtime` | Alamat WebSocket Reverb untuk klien |
| POST | `/api/sensor-readings` | Untuk gateway sensor, wajib header `X-Sensor-Token` |

Contoh kiriman gateway:

```json
{
  "readings": [
    { "sensor_id": "US-P1-A001", "distance_cm": 82, "measured_at": "2026-10-24T08:50:00+07:00" },
    { "sensor_id": "US-P1-A002", "distance_cm": 255 }
  ]
}
```

Balasan berisi jumlah pembacaan yang diterima (`accepted`), jumlah slot yang berubah status (`changed`), dan `unknown_sensors` (ID sensor yang belum terdaftar ke slot mana pun).

## Event realtime

| Channel | Event | Isi |
| --- | --- | --- |
| `floors` | `floor.summary` | Ringkasan satu lantai |
| `floor.{id}` | `floor.summary` | Ringkasan lantai itu |
| `floor.{id}` | `slots.changed` | Daftar slot yang berubah (`id`, `code`, `zone`, `status`), maksimal 100 per pesan |

## Belum dikerjakan

- Dashboard admin untuk sensor yang offline.
- Penyesuaian kode slot, zona, dan ID sensor dengan instalasi asli di mall.
- Notifikasi push ("lantai hampir penuh") di aplikasi mobile.
