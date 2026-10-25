import 'package:flutter/widgets.dart';

import 'api.dart';
import 'realtime.dart';

/// Menyediakan API dan satu koneksi realtime bersama untuk semua layar.
class AppScope extends StatefulWidget {
  const AppScope({super.key, required this.api, required this.child});

  final ParkirApi api;
  final Widget child;

  static AppScopeState of(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<_AppScopeData>()!.state;

  @override
  State<AppScope> createState() => AppScopeState();
}

class AppScopeState extends State<AppScope> {
  Future<RealtimeClient?>? _realtime;

  ParkirApi get api => widget.api;

  /// Koneksi realtime dibuat sekali, saat pertama dibutuhkan. Jika gagal,
  /// aplikasi tetap jalan dan hanya memuat ulang data secara berkala.
  Future<RealtimeClient?> get realtime => _realtime ??= api
      .realtimeConfig()
      .then<RealtimeClient?>((config) => config.isEnabled ? RealtimeClient(config) : null)
      .catchError((_) {
        _realtime = null;
        return null;
      });

  @override
  void dispose() {
    _realtime?.then((client) => client?.close());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => _AppScopeData(state: this, child: widget.child);
}

class _AppScopeData extends InheritedWidget {
  const _AppScopeData({required this.state, required super.child});

  final AppScopeState state;

  @override
  bool updateShouldNotify(_AppScopeData oldWidget) => false;
}
