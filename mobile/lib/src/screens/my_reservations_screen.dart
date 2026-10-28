import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../models.dart';
import '../status_colors.dart';
import 'reservation_screen.dart';

/// Reservasi yang pernah dibuat dari HP ini.
class MyReservationsScreen extends StatefulWidget {
  const MyReservationsScreen({super.key});

  @override
  State<MyReservationsScreen> createState() => _MyReservationsScreenState();
}

class _MyReservationsScreenState extends State<MyReservationsScreen> {
  List<Reservation>? _reservations;
  String? _error;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_reservations == null && _error == null) _load();
  }

  Future<void> _load() async {
    final scope = AppScope.of(context);
    try {
      final codes = await scope.reservationCodes.load();
      final results = await Future.wait(
        codes.map((code) => scope.api.reservation(code).then<Reservation?>((r) => r).catchError((_) => null)),
      );
      if (mounted) setState(() => (_reservations = results.whereType<Reservation>().toList(), _error = null));
    } catch (e) {
      if (mounted) setState(() => _error = 'Gagal memuat reservasi. Tarik ke bawah untuk mencoba lagi.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final reservations = _reservations;

    return Scaffold(
      appBar: AppBar(title: const Text('Reservasi saya')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          children: [
            if (_error != null) Padding(padding: const EdgeInsets.all(16), child: Text(_error!)),
            if (reservations == null && _error == null)
              const Padding(
                padding: EdgeInsets.all(32),
                child: Center(child: CircularProgressIndicator()),
              ),
            if (reservations != null && reservations.isEmpty)
              const Padding(
                padding: EdgeInsets.all(16),
                child: Text('Belum ada reservasi. Pilih lantai, lalu ketuk slot hijau untuk reservasi.'),
              ),
            for (final r in reservations ?? <Reservation>[])
              ListTile(
                leading: CircleAvatar(backgroundColor: reservationColor(r.status), radius: 8),
                title: Text('${r.slotCode} · ${r.floorName}'),
                subtitle: Text('${r.plateNumber} · ${r.statusLabel}'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () async {
                  await Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => ReservationScreen(code: r.code, initial: r),
                    ),
                  );
                  _load();
                },
              ),
          ],
        ),
      ),
    );
  }
}
