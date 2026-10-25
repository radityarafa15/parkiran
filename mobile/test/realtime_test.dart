import 'dart:async';
import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:parkiran/src/models.dart';
import 'package:parkiran/src/realtime.dart';
import 'package:stream_channel/stream_channel.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

/// Soket palsu: [server] berisi pesan dari aplikasi, [toApp] mengirim ke aplikasi.
class FakeSocket extends StreamChannelMixin<dynamic> implements WebSocketChannel {
  final toApp = StreamController<dynamic>();
  final server = <Map<String, dynamic>>[];
  late final _sink = _FakeSink(server);

  @override
  Stream<dynamic> get stream => toApp.stream;

  @override
  WebSocketSink get sink => _sink;

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _FakeSink implements WebSocketSink {
  _FakeSink(this.sent);

  final List<Map<String, dynamic>> sent;

  @override
  void add(dynamic data) => sent.add(jsonDecode(data as String) as Map<String, dynamic>);

  @override
  Future<void> close([int? closeCode, String? closeReason]) async {}

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

void main() {
  const config = RealtimeConfig(key: 'abc', host: 'parkir.test', port: 443, scheme: 'https');

  test('socket uri uses wss for https', () {
    expect(config.socketUri.toString(), startsWith('wss://parkir.test:443/app/abc?protocol=7'));
  });

  test('subscribes after connection, answers ping, and emits channel events', () async {
    final socket = FakeSocket();
    final client = RealtimeClient(config, connect: (_) => socket);
    final received = <RealtimeEvent>[];
    client.events.listen(received.add);

    client.subscribe('floor.1');
    client.handleMessage(jsonEncode({'event': 'pusher:connection_established', 'data': '{"socket_id":"1.2"}'}));
    client.handleMessage(jsonEncode({'event': 'pusher:ping', 'data': '{}'}));
    client.handleMessage(jsonEncode({
      'event': 'slots.changed',
      'channel': 'floor.1',
      'data': jsonEncode({
        'slots': [
          {'id': 7, 'code': 'P1-A007', 'zone': 'A', 'status': 'occupied'},
        ],
      }),
    }));
    await Future<void>.delayed(Duration.zero);

    expect(socket.server.where((m) => m['event'] == 'pusher:subscribe').last['data'], {'channel': 'floor.1'});
    expect(socket.server.any((m) => m['event'] == 'pusher:pong'), isTrue);
    expect(received.single.event, 'slots.changed');
    final slot = Slot.fromJson((received.single.data['slots'] as List).single as Map<String, dynamic>);
    expect(slot.status, SlotStatus.occupied);
    expect(slot.shortCode, 'A007');
  });
}
