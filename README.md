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

Yang perlu terpasang: PHP 8.3+ (dengan ekstensi `pdo_sqlite`), Composer, dan Node.js (dipakai `composer dev` untuk menjalankan beberapa proses sekaligus).

```bash
composer setup   # sekali saja: .env, kunci, database SQLite, 6.995 slot, akun admin
composer dev     # web + Reverb (realtime) + scheduler + simulator sensor
```

`composer setup` mencetak email dan kata sandi admin. Lupa kata sandi? Jalankan `php artisan parkir:make-admin admin@parkiran.test` untuk mengaturnya ulang.

Lalu buka:

- http://localhost:8000: halaman pengunjung (daftar lantai, lalu slot per lantai)
- http://localhost:8000/admin: dashboard sensor offline

Hentikan dengan Ctrl+C. Tanpa Node.js, jalankan empat proses ini di terminal terpisah:

```bash
php artisan serve
php artisan reverb:start
php artisan schedule:work
php artisan parkir:simulate
```

Perintah lain:

```bash
php artisan parkir:install --fresh   # hapus semua data lalu isi ulang 6.995 slot
php artisan test
```

## Reservasi slot

1. Pengunjung mengetuk slot hijau, mengisi nama dan plat nomor. Slot langsung ditahan (tampil biru) selama `PARKIR_RESERVATION_PAYMENT_MINUTES` (default 15 menit) sambil menunggu pembayaran.
2. Biaya admin `PARKIR_RESERVATION_FEE` (default Rp10.000) dibayar. **Pembayaran masih simulasi**: tombol "Bayar" langsung menandai lunas. Payment gateway (misalnya Midtrans) cukup memanggil `ReservationService::pay()` setelah transaksi sukses.
3. Setelah dibayar, pengunjung mendapat QR berisi kode reservasi. QR berlaku `PARKIR_RESERVATION_VALID_MINUTES` (default 120 menit). Jika belum di-scan di gerbang masuk sebelum itu, reservasi hangus dan biaya tidak dikembalikan.
4. Petugas membuka `/admin/gerbang` (perlu login admin). Scanner QR USB mengetik kode lalu Enter; kode juga bisa diketik manual. Scan di **gerbang masuk** memulai jam parkir. Slot masih ditahan `PARKIR_RESERVATION_DRIVE_IN_MINUTES` (default 15 menit) selama pengemudi menuju slotnya. Scan di **gerbang keluar** menghentikan jam parkir dan menampilkan durasinya (dibulatkan ke atas per jam).
5. Perintah terjadwal `parkir:expire-reservations` (tiap menit) menandai reservasi yang hangus dan melepas slotnya. Jika sensor mendeteksi mobil di slot yang direservasi, slot tetap tampil terisi.

Reservasi yang dibuat di satu browser bisa dibuka lagi di `/reservasi`; di aplikasi mobile lewat ikon tiket di daftar lantai. Halaman reservasi menampilkan hitung mundur sampai batas bayar atau batas masuk, lalu lama parkir setelah masuk.

Di halaman gerbang, tombol **Scan pakai kamera** membaca QR lewat kamera HP atau laptop petugas. Browser hanya mengizinkan kamera di `https://` atau di `localhost`/`127.0.0.1`, jadi untuk HP petugas di jaringan lokal perlu https.

Ringkasan reservasi dan pendapatan biaya admin per hari ada di `/admin/laporan`.

## Denah

Halaman lantai (web dan mobile) menampilkan denah skematis: peta zona berwarna sesuai sisa slot kosong, lalu tiap zona berupa lajur berisi dua baris slot yang berhadapan dengan jalur mobil di tengah (`config/parkir.php` → `map.slots_per_row`). Susunan ini belum mengikuti denah asli mall.

## API

| Metode | Path | Keterangan |
| --- | --- | --- |
| GET | `/api/floors` | Ringkasan tiap lantai: total, kosong, terisi, direservasi, tidak diketahui |
| GET | `/api/floors/{id}` | Ringkasan lantai dan per zona |
| GET | `/api/floors/{id}/slots?zone=A` | Status slot di lantai, bisa disaring per zona |
| GET | `/api/realtime` | Alamat WebSocket Reverb untuk klien |
| POST | `/api/reservations` | Buat reservasi: `slot_id`, `name`, `plate_number`. Balas 409 jika slot tidak tersedia |
| GET | `/api/reservations/{kode}` | Status reservasi, batas waktu, lama parkir, dan `qr_svg_url` |
| POST | `/api/reservations/{kode}/pay` | Bayar biaya admin (simulasi) |
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

- Payment gateway sungguhan untuk biaya admin reservasi.
- Tarif parkir per jam (sekarang hanya durasi dan jumlah jam yang dihitung).
- Penyesuaian kode slot, zona, dan ID sensor dengan instalasi asli di mall.
- Notifikasi push ("lantai hampir penuh") di aplikasi mobile.
