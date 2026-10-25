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
    | Jumlah slot per zona saat seeding (zona A, B, C, ...).
    */
    'zone_size' => 100,

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
