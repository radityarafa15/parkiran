# Parkiran (mobile)

Aplikasi Flutter untuk pengunjung mall: daftar lantai dengan jumlah slot kosong, lalu denah slot per zona. Slot hijau bisa diketuk untuk reservasi (biaya admin, QR untuk gerbang). Status diperbarui langsung lewat WebSocket (Laravel Reverb), dengan muat ulang penuh tiap 60 detik sebagai cadangan.

## Menjalankan

Backend Laravel dan Reverb harus sudah berjalan (lihat README di root repo).

```bash
cd mobile
flutter pub get
# Emulator Android menjangkau komputer lewat 10.0.2.2
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
# Perangkat sungguhan / produksi
flutter run --dart-define=API_BASE_URL=https://parkir.mall-anda.id
```

Alamat WebSocket diambil dari `GET /api/realtime`, jadi `REVERB_HOST` dan `REVERB_PORT` di `.env` backend harus berisi alamat yang bisa dijangkau ponsel (bukan `localhost`).

Catatan: build debug Android mengizinkan http biasa untuk server lokal. Build rilis dan iOS memerlukan https/wss.

## Struktur

- `lib/src/api.dart`: klien REST (`/api/floors`, `/api/floors/{id}/slots`, `/api/realtime`, `/api/reservations`).
- `lib/src/reservation_codes.dart`: kode reservasi yang dibuat dari HP ini (disimpan dengan `shared_preferences`).
- `lib/src/realtime.dart`: klien protokol Pusher minimal untuk Reverb (channel publik, ping/pong, sambung ulang otomatis).
- `lib/src/screens/`: daftar lantai, layar lantai (pilih zona, denah lajur), formulir reservasi, layar reservasi (hitung mundur, QR), dan daftar reservasi saya.

## Tes

```bash
flutter analyze
flutter test
```
