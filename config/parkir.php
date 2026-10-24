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

];
