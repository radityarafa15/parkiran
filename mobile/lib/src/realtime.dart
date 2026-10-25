import 'dart:async';
import 'dart:convert';

import 'package:web_socket_channel/web_socket_channel.dart';

import 'models.dart';

/// Event yang diterima dari channel publik Reverb.
class RealtimeEvent {
  const RealtimeEvent(this.channel, this.event, this.data);

  final String channel;
  final String event;
  final Map<String, dynamic> data;
}

/// Klien minimal protokol Pusher untuk Laravel Reverb: hanya channel publik,
/// cukup untuk menerima pembaruan status parkir. Tersambung ulang otomatis.
class RealtimeClient {
  RealtimeClient(this.config, {WebSocketChannel Function(Uri uri)? connect})
      : _connect = connect ?? WebSocketChannel.connect;

  final RealtimeConfig config;
  final WebSocketChannel Function(Uri uri) _connect;
  final _events = StreamController<RealtimeEvent>.broadcast();
  final _channels = <String>{};

  WebSocketChannel? _socket;
  StreamSubscription<dynamic>? _subscription;
  Timer? _reconnectTimer;
  var _attempt = 0;
  var _closed = false;

  Stream<RealtimeEvent> get events => _events.stream;

  void subscribe(String channel) {
    if (_channels.add(channel)) _send('pusher:subscribe', {'channel': channel});
    _ensureConnected();
  }

  void unsubscribe(String channel) {
    if (_channels.remove(channel)) _send('pusher:unsubscribe', {'channel': channel});
  }

  void _ensureConnected() {
    if (_socket != null || _closed || !config.isEnabled) return;

    final socket = _connect(config.socketUri);
    _socket = socket;
    _subscription = socket.stream.listen(
      (message) => handleMessage(message as String),
      onDone: _scheduleReconnect,
      onError: (_) => _scheduleReconnect(),
    );
  }

  /// Diproses terpisah agar bisa diuji tanpa koneksi sungguhan.
  void handleMessage(String message) {
    final payload = jsonDecode(message) as Map<String, dynamic>;
    final event = payload['event'] as String?;
    final raw = payload['data'];
    final data = raw is String ? (raw.isEmpty ? <String, dynamic>{} : jsonDecode(raw)) : raw;

    switch (event) {
      case 'pusher:connection_established':
        _attempt = 0;
        for (final channel in _channels) {
          _send('pusher:subscribe', {'channel': channel});
        }
      case 'pusher:ping':
        _send('pusher:pong', {});
      case null:
        break;
      default:
        if (!event.startsWith('pusher') && payload['channel'] is String && data is Map<String, dynamic>) {
          _events.add(RealtimeEvent(payload['channel'] as String, event, data));
        }
    }
  }

  void _send(String event, Map<String, dynamic> data) {
    _socket?.sink.add(jsonEncode({'event': event, 'data': data}));
  }

  void _scheduleReconnect() {
    _subscription?.cancel();
    _socket = null;
    if (_closed) return;

    // Jeda bertambah sampai 30 detik.
    _attempt++;
    final delay = Duration(seconds: (_attempt * 2).clamp(2, 30));
    _reconnectTimer?.cancel();
    _reconnectTimer = Timer(delay, _ensureConnected);
  }

  Future<void> close() async {
    _closed = true;
    _reconnectTimer?.cancel();
    await _subscription?.cancel();
    await _socket?.sink.close();
    await _events.close();
  }
}
