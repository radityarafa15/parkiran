<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Menyusun slot satu zona ke denah blok (config parkir.map): baris berisi
 * kolom antar-tiang, tiap kolom berupa bay berisi beberapa slot, CORE, atau kosong.
 */
class FloorPlan
{
    /**
     * @template T
     *
     * @param  Collection<int, T>|array<int, T>  $slots  slot zona, urut sesuai nomor
     * @return array<int, array<int, array{type: 'bay', slots: array<int, T|null>}|array{type: 'core'}|array{type: 'empty'}>>
     */
    public static function rows(Collection|array $slots): array
    {
        $map = config('parkir.map');
        $slots = array_values(collect($slots)->all());
        $next = 0;
        $rows = [];

        foreach ($map['rows'] as $row) {
            $cells = [];

            for ($column = 1; $column <= $map['columns']; $column++) {
                if (in_array($column, $row['bays'], true)) {
                    $bay = [];
                    for ($i = 0; $i < $map['slots_per_bay']; $i++) {
                        // Blok terakhir di lantai bisa tidak penuh.
                        $bay[] = $slots[$next++] ?? null;
                    }
                    $cells[] = ['type' => 'bay', 'slots' => $bay];
                } elseif (($row['core'] ?? null) === $column) {
                    $cells[] = ['type' => 'core'];
                } else {
                    $cells[] = ['type' => 'empty'];
                }
            }

            $rows[] = $cells;
        }

        return $rows;
    }
}
