/// Status slot seperti yang dikirim server.
enum SlotStatus {
  available,
  occupied,
  unknown;

  static SlotStatus parse(String? value) => switch (value) {
        'available' => SlotStatus.available,
        'occupied' => SlotStatus.occupied,
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
