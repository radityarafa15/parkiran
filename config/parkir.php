<?php

return [

    /*
    | Token yang wajib dikirim gateway sensor di header X-Sensor-Token.
    */
    'sensor_token' => env('PARKIR_SENSOR_TOKEN'),

    /*
    | Sensor ultrasonik dipasang di plafon. Jarak terukur di bawah ambang ini
    | berarti ada mobil di bawah sensor (slot terisi).
    */
    'occupied_threshold_cm' => (int) env('PARKIR_OCCUPIED_THRESHOLD_CM', 150),

    /*
    | Slot yang sensornya tidak melapor lebih lama dari ini dianggap
    | "tidak diketahui", bukan "kosong".
    */
    'stale_after_seconds' => (int) env('PARKIR_STALE_AFTER_SECONDS', 300),

    /*
    | Denah awal untuk seeder: jumlah slot mobil per lantai (total 6.995).
    | Pembagian per lantai masih perkiraan; sesuaikan dengan denah mall.
    */
    'layout' => [
        1 => 2332,
        2 => 2332,
        3 => 2331,
    ],

    /*
    | Denah satu blok parkir sesuai gambar denah lantai dari pengelola:
    | 8 kolom antar-tiang; tiap kotak (bay) berisi 3 slot berdampingan.
    | Baris atas 7 bay, pulau tengah 6 bay (kolom 2-7), baris bawah 7 bay dan
    | CORE (lift/tangga) di kolom 8. Slot dinomori kiri ke kanan, atas ke bawah,
    | jadi satu blok berisi 60 slot. Satu zona (A, B, ...) = satu blok.
    */
    'map' => $map = [
        'columns' => 8,
        'slots_per_bay' => 3,
        'rows' => [
            ['bays' => [1, 2, 3, 4, 5, 6, 7]],
            ['bays' => [2, 3, 4, 5, 6, 7]],
            ['bays' => [1, 2, 3, 4, 5, 6, 7], 'core' => 8],
        ],
    ],

    /*
    | Jumlah slot per zona saat seeding: satu blok denah di atas.
    */
    'zone_size' => array_sum(array_map(fn ($row) => count($row['bays']), $map['rows'])) * $map['slots_per_bay'],

    /*
    | Reservasi slot: biaya admin (rupiah), batas waktu bayar, dan masa berlaku
    | setelah dibayar. Reservasi hangus jika QR belum di-scan di gerbang masuk
    | sebelum masa berlakunya habis; biaya tidak dikembalikan. Setelah masuk,
    | slot masih ditahan sebentar selama pengemudi menuju slotnya.
    */
    /*
    | Tarif parkir per jam (rupiah). Lama parkir dihitung dari scan gerbang
    | masuk sampai scan gerbang keluar, dibulatkan ke atas per jam.
    */
    'hourly_rate' => (int) env('PARKIR_HOURLY_RATE', 5000),

    'reservation' => [
        'fee' => (int) env('PARKIR_RESERVATION_FEE', 10000),
        'payment_minutes' => (int) env('PARKIR_RESERVATION_PAYMENT_MINUTES', 15),
        'valid_minutes' => (int) env('PARKIR_RESERVATION_VALID_MINUTES', 120),
        'drive_in_minutes' => (int) env('PARKIR_RESERVATION_DRIVE_IN_MINUTES', 15),
    ],

    /*
    | Koneksi WebSocket (Laravel Reverb) yang dipakai web dan aplikasi mobile.
    | Host dan port di sini adalah alamat yang dijangkau pengguna, bukan alamat
    | internal server Reverb.
    */
    'realtime' => [
        'key' => env('REVERB_APP_KEY'),
        'host' => env('REVERB_HOST', 'localhost'),
        'port' => (int) env('REVERB_PORT', 8080),
        'scheme' => env('REVERB_SCHEME', 'http'),
    ],

];
