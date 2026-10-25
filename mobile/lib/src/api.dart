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
}

class ApiException implements Exception {
  ApiException(this.message);

  final String message;

  @override
  String toString() => message;
}
