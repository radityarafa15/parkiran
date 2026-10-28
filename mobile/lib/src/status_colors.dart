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
