import 'package:flutter/material.dart';

import 'models.dart';

const availableColor = Color(0xFF1F9D55);
const occupiedColor = Color(0xFFD64545);
const unknownColor = Color(0xFF9AA5B1);
const busyColor = Color(0xFFD9822B);
const reservedColor = Color(0xFF3B6FD8);

Color slotColor(SlotStatus status) => switch (status) {
  SlotStatus.available => availableColor,
  SlotStatus.occupied => occupiedColor,
  SlotStatus.reserved => reservedColor,
  SlotStatus.unknown => unknownColor,
};

Color floorColor(FloorSummary floor) =>
    floor.isFull ? occupiedColor : (floor.isAlmostFull ? busyColor : availableColor);

/// 2332 -> "2.332"
String formatCount(int n) => n.toString().replaceAllMapped(RegExp(r'\B(?=(\d{3})+$)'), (_) => '.');

/// 10000 -> "Rp10.000"
String formatRupiah(int amount) => 'Rp${formatCount(amount)}';

/// 150 -> "2 jam 30 menit"
String formatMinutes(int minutes) => minutes >= 60 ? '${minutes ~/ 60} jam ${minutes % 60} menit' : '$minutes menit';

/// Durasi sebagai jam berjalan, misalnya "1:05:09".
String formatClock(Duration d) {
  final s = d.isNegative ? 0 : d.inSeconds;
  String two(int n) => n.toString().padLeft(2, '0');
  return '${s ~/ 3600}:${two(s ~/ 60 % 60)}:${two(s % 60)}';
}

/// Jam lokal HP, misalnya "14:05".
String formatTime(DateTime t) => '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

Color reservationColor(ReservationStatus status) => switch (status) {
  ReservationStatus.pending => busyColor,
  ReservationStatus.paid => reservedColor,
  ReservationStatus.parked || ReservationStatus.done => availableColor,
  ReservationStatus.expired => occupiedColor,
};
