/// Status slot seperti yang dikirim server.
enum SlotStatus {
  available,
  occupied,
  reserved,
  unknown;

  static SlotStatus parse(String? value) => switch (value) {
    'available' => SlotStatus.available,
    'occupied' => SlotStatus.occupied,
    'reserved' => SlotStatus.reserved,
    _ => SlotStatus.unknown,
  };
}

class FloorSummary {
  const FloorSummary({
    required this.id,
    required this.name,
    required this.level,
    required this.total,
    required this.available,
    required this.occupied,
    required this.unknown,
  });

  factory FloorSummary.fromJson(Map<String, dynamic> json) => FloorSummary(
    id: json['id'] as int,
    name: json['name'] as String,
    level: json['level'] as int,
    total: json['total'] as int,
    available: json['available'] as int,
    occupied: json['occupied'] as int,
    unknown: json['unknown'] as int,
  );

  final int id;
  final String name;
  final int level;
  final int total;
  final int available;
  final int occupied;
  final int unknown;

  bool get isFull => available == 0;

  /// Sisa slot 10% atau kurang dianggap hampir penuh.
  bool get isAlmostFull => !isFull && available <= total * 0.1;
}

class Slot {
  const Slot({required this.id, required this.code, required this.zone, required this.status});

  factory Slot.fromJson(Map<String, dynamic> json) => Slot(
    id: json['id'] as int,
    code: json['code'] as String,
    zone: json['zone'] as String,
    status: SlotStatus.parse(json['status'] as String?),
  );

  final int id;
  final String code;
  final String zone;
  final SlotStatus status;

  /// Kode tanpa awalan lantai, misalnya "A001" dari "P1-A001".
  String get shortCode => code.contains('-') ? code.substring(code.indexOf('-') + 1) : code;

  /// Nomor slot di dalam zonanya, misalnya "001" dari "P1-A001".
  String get number => shortCode.startsWith(zone) ? shortCode.substring(zone.length) : shortCode;
}

class RealtimeConfig {
  const RealtimeConfig({required this.key, required this.host, required this.port, required this.scheme});

  factory RealtimeConfig.fromJson(Map<String, dynamic> json) => RealtimeConfig(
    key: json['key'] as String? ?? '',
    host: json['host'] as String? ?? 'localhost',
    port: json['port'] as int? ?? 8080,
    scheme: json['scheme'] as String? ?? 'http',
  );

  final String key;
  final String host;
  final int port;
  final String scheme;

  bool get isEnabled => key.isNotEmpty;

  Uri get socketUri => Uri(
    scheme: scheme == 'https' ? 'wss' : 'ws',
    host: host,
    port: port,
    path: '/app/$key',
    queryParameters: {'protocol': '7', 'client': 'parkiran-flutter', 'version': '1.0'},
  );
}

/// Status reservasi seperti yang dikirim server.
enum ReservationStatus {
  pending,
  paid,
  parked,
  done,
  expired;

  static ReservationStatus parse(String? value) =>
      ReservationStatus.values.firstWhere((s) => s.name == value, orElse: () => ReservationStatus.expired);

  /// Reservasi yang masih berjalan; halamannya perlu diperbarui berkala.
  bool get isActive => this == pending || this == paid || this == parked;
}

class Reservation {
  const Reservation({
    required this.code,
    required this.status,
    required this.statusLabel,
    required this.name,
    required this.plateNumber,
    required this.fee,
    required this.slotCode,
    required this.floorName,
    required this.payBefore,
    this.paidAt,
    this.expiresAt,
    this.checkedInAt,
    this.checkedOutAt,
    this.parkedMinutes,
    this.parkedHours,
    this.hourlyRate,
    this.parkingFee,
  });

  factory Reservation.fromJson(Map<String, dynamic> json) {
    DateTime? time(String key) => json[key] == null ? null : DateTime.parse(json[key] as String).toLocal();
    final slot = json['slot'] as Map<String, dynamic>;

    return Reservation(
      code: json['code'] as String,
      status: ReservationStatus.parse(json['status'] as String?),
      statusLabel: json['status_label'] as String,
      name: json['name'] as String,
      plateNumber: json['plate_number'] as String,
      fee: json['fee'] as int,
      slotCode: slot['code'] as String,
      floorName: slot['floor'] as String,
      payBefore: time('pay_before')!,
      paidAt: time('paid_at'),
      expiresAt: time('expires_at'),
      checkedInAt: time('checked_in_at'),
      checkedOutAt: time('checked_out_at'),
      parkedMinutes: json['parked_minutes'] as int?,
      parkedHours: json['parked_hours'] as int?,
      hourlyRate: json['hourly_rate'] as int?,
      parkingFee: json['parking_fee'] as int?,
    );
  }

  final String code;
  final ReservationStatus status;
  final String statusLabel;
  final String name;
  final String plateNumber;
  final int fee;
  final String slotCode;
  final String floorName;
  final DateTime payBefore;
  final DateTime? paidAt;
  final DateTime? expiresAt;
  final DateTime? checkedInAt;
  final DateTime? checkedOutAt;
  final int? parkedMinutes;
  final int? parkedHours;
  final int? hourlyRate;

  /// Biaya parkir: ditagih saat keluar, atau perkiraan selama masih parkir.
  final int? parkingFee;

  /// Batas waktu yang sedang berjalan: bayar, lalu masuk gerbang.
  DateTime? get deadline => switch (status) {
    ReservationStatus.pending => payBefore,
    ReservationStatus.paid => expiresAt,
    _ => null,
  };
}

/// Denah satu blok (= satu zona), sama di semua lantai. Lihat config parkir.map.
class FloorPlan {
  const FloorPlan({required this.columns, required this.slotsPerBay, required this.rows});

  factory FloorPlan.fromJson(Map<String, dynamic> json) => FloorPlan(
    columns: json['columns'] as int,
    slotsPerBay: json['slots_per_bay'] as int,
    rows: [
      for (final row in json['rows'] as List<dynamic>)
        PlanRow(
          bays: [for (final c in (row as Map<String, dynamic>)['bays'] as List<dynamic>) c as int],
          core: row['core'] as int?,
        ),
    ],
  );

  /// Denah asli dari pengelola: atas 7 bay, tengah 6 bay, bawah 7 bay + CORE.
  /// Dipakai jika server belum menyediakan /api/floor-plan.
  static const standard = FloorPlan(
    columns: 8,
    slotsPerBay: 3,
    rows: [
      PlanRow(bays: [1, 2, 3, 4, 5, 6, 7]),
      PlanRow(bays: [2, 3, 4, 5, 6, 7]),
      PlanRow(bays: [1, 2, 3, 4, 5, 6, 7], core: 8),
    ],
  );

  final int columns;
  final int slotsPerBay;
  final List<PlanRow> rows;
}

class PlanRow {
  const PlanRow({required this.bays, this.core});

  /// Kolom (mulai 1) yang berisi bay slot, dinomori kiri ke kanan.
  final List<int> bays;

  /// Kolom CORE (lift/tangga), jika ada di baris ini.
  final int? core;
}
