import 'dart:convert';

import 'package:http/http.dart' as http;

import 'models.dart';

class ParkirApi {
  ParkirApi(this.baseUrl, {http.Client? client}) : _client = client ?? http.Client();

  final String baseUrl;
  final http.Client _client;

  Future<List<FloorSummary>> floors() async {
    final data = await _get('/api/floors') as List<dynamic>;
    return data.map((f) => FloorSummary.fromJson(f as Map<String, dynamic>)).toList();
  }

  Future<FloorSummary> floor(int id) async {
    return FloorSummary.fromJson(await _get('/api/floors/$id') as Map<String, dynamic>);
  }

  Future<List<Slot>> slots(int floorId) async {
    final data = await _get('/api/floors/$floorId/slots') as List<dynamic>;
    return data.map((s) => Slot.fromJson(s as Map<String, dynamic>)).toList();
  }

  Future<Reservation> createReservation({
    required int slotId,
    required String name,
    required String plateNumber,
  }) async {
    return Reservation.fromJson(
      await _post('/api/reservations', {'slot_id': slotId, 'name': name, 'plate_number': plateNumber})
          as Map<String, dynamic>,
    );
  }

  Future<Reservation> reservation(String code) async {
    return Reservation.fromJson(await _get('/api/reservations/$code') as Map<String, dynamic>);
  }

  /// Bayar biaya admin. Masih simulasi di server.
  Future<Reservation> payReservation(String code) async {
    return Reservation.fromJson(await _post('/api/reservations/$code/pay', const {}) as Map<String, dynamic>);
  }

  Future<RealtimeConfig> realtimeConfig() async {
    return RealtimeConfig.fromJson(await _get('/api/realtime') as Map<String, dynamic>);
  }

  Future<Object?> _get(String path) async {
    final response = await _client.get(Uri.parse('$baseUrl$path'), headers: {'Accept': 'application/json'});
    if (response.statusCode != 200) {
      throw ApiException('Server membalas ${response.statusCode}');
    }
    return (jsonDecode(response.body) as Map<String, dynamic>)['data'];
  }

  Future<Object?> _post(String path, Map<String, Object?> body) async {
    final response = await _client.post(
      Uri.parse('$baseUrl$path'),
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
      body: jsonEncode(body),
    );
    final json = response.body.isEmpty ? <String, dynamic>{} : jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 400) {
      // 409: slot sudah diambil orang lain; 422: isian tidak valid.
      final errors = json['errors'] as Map<String, dynamic>?;
      final first = errors?.values.whereType<List<dynamic>>().firstOrNull?.firstOrNull;
      throw ApiException((first ?? json['message'] ?? 'Server membalas ${response.statusCode}').toString());
    }
    return json['data'];
  }
}

class ApiException implements Exception {
  ApiException(this.message);

  final String message;

  @override
  String toString() => message;
}
