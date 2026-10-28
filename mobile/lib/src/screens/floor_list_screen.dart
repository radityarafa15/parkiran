import 'dart:async';

import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../models.dart';
import '../realtime.dart';
import '../status_colors.dart';
import 'floor_screen.dart';
import 'my_reservations_screen.dart';

class FloorListScreen extends StatefulWidget {
  const FloorListScreen({super.key});

  @override
  State<FloorListScreen> createState() => _FloorListScreenState();
}

class _FloorListScreenState extends State<FloorListScreen> {
  List<FloorSummary>? _floors;
  String? _error;
  Timer? _refreshTimer;
  StreamSubscription<RealtimeEvent>? _events;
  RealtimeClient? _realtime;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_refreshTimer != null) return;

    _load();
    // Cadangan jika koneksi realtime putus.
    _refreshTimer = Timer.periodic(const Duration(seconds: 60), (_) => _load());
    AppScope.of(context).realtime.then((client) {
      if (!mounted || client == null) return;
      _realtime = client..subscribe('floors');
      _events = client.events.where((e) => e.channel == 'floors' && e.event == 'floor.summary').listen((e) {
        final updated = FloorSummary.fromJson(e.data);
        setState(() => _floors = [for (final f in _floors ?? <FloorSummary>[]) f.id == updated.id ? updated : f]);
      });
    });
  }

  Future<void> _load() async {
    try {
      final floors = await AppScope.of(context).api.floors();
      if (mounted) setState(() => (_floors = floors, _error = null));
    } catch (e) {
      if (mounted) setState(() => _error = 'Gagal memuat data parkir. Tarik ke bawah untuk mencoba lagi.');
    }
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _events?.cancel();
    _realtime?.unsubscribe('floors');
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final floors = _floors;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Ketersediaan parkir'),
        actions: [
          IconButton(
            tooltip: 'Reservasi saya',
            icon: const Icon(Icons.confirmation_number_outlined),
            onPressed: () =>
                Navigator.of(context).push(MaterialPageRoute(builder: (_) => const MyReservationsScreen())),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (_error != null) Padding(padding: const EdgeInsets.only(bottom: 12), child: Text(_error!)),
            if (floors == null && _error == null) const Center(child: CircularProgressIndicator()),
            for (final floor in floors ?? <FloorSummary>[]) _FloorCard(floor: floor),
          ],
        ),
      ),
    );
  }
}

class _FloorCard extends StatelessWidget {
  const _FloorCard({required this.floor});

  final FloorSummary floor;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => FloorScreen(floor: floor))),
        child: Container(
          decoration: BoxDecoration(
            border: Border(left: BorderSide(color: floorColor(floor), width: 6)),
          ),
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(floor.name, style: theme.textTheme.titleMedium),
                    const SizedBox(height: 4),
                    Text(
                      floor.isFull
                          ? 'Penuh'
                          : '${formatCount(floor.occupied)} terisi · ${formatCount(floor.total)} total',
                      style: theme.textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    formatCount(floor.available),
                    style: theme.textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  Text('slot kosong', style: theme.textTheme.bodySmall),
                ],
              ),
              const SizedBox(width: 8),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}
