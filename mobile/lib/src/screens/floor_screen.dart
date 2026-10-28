import 'dart:async';

import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../models.dart';
import '../realtime.dart';
import '../status_colors.dart';

/// Satu lantai: pilih zona, lalu lihat status tiap slot.
class FloorScreen extends StatefulWidget {
  const FloorScreen({super.key, required this.floor});

  final FloorSummary floor;

  @override
  State<FloorScreen> createState() => _FloorScreenState();
}

class _FloorScreenState extends State<FloorScreen> {
  /// Slot per id; diperbarui di tempat saat event realtime masuk.
  final _slots = <int, Slot>{};
  List<String> _zones = [];
  String? _zone;
  bool _loading = true;
  String? _error;
  Timer? _refreshTimer;
  StreamSubscription<RealtimeEvent>? _events;
  RealtimeClient? _realtime;

  String get _channel => 'floor.${widget.floor.id}';

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_refreshTimer != null) return;

    _load();
    _refreshTimer = Timer.periodic(const Duration(seconds: 60), (_) => _load());
    AppScope.of(context).realtime.then((client) {
      if (!mounted || client == null) return;
      _realtime = client..subscribe(_channel);
      _events = client.events.where((e) => e.channel == _channel && e.event == 'slots.changed').listen((e) {
        setState(() {
          for (final raw in e.data['slots'] as List<dynamic>) {
            final slot = Slot.fromJson(raw as Map<String, dynamic>);
            _slots[slot.id] = slot;
          }
        });
      });
    });
  }

  Future<void> _load() async {
    try {
      final slots = await AppScope.of(context).api.slots(widget.floor.id);
      if (!mounted) return;
      setState(() {
        _slots
          ..clear()
          ..addEntries(slots.map((s) => MapEntry(s.id, s)));
        _zones = slots.map((s) => s.zone).toSet().toList()..sort();
        _zone ??= _zones.isEmpty ? null : _zones.first;
        _loading = false;
        _error = null;
      });
    } catch (e) {
      if (mounted) setState(() => (_loading = false, _error = 'Gagal memuat slot. Tarik ke bawah untuk mencoba lagi.'));
    }
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _events?.cancel();
    _realtime?.unsubscribe(_channel);
    super.dispose();
  }

  int _availableIn(String? zone) =>
      _slots.values.where((s) => (zone == null || s.zone == zone) && s.status == SlotStatus.available).length;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final zoneSlots = _slots.values.where((s) => s.zone == _zone).toList()..sort((a, b) => a.code.compareTo(b.code));

    return Scaffold(
      appBar: AppBar(title: Text(widget.floor.name)),
      body: RefreshIndicator(
        onRefresh: _load,
        child: CustomScrollView(
          slivers: [
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
              sliver: SliverToBoxAdapter(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (_error != null) Padding(padding: const EdgeInsets.only(bottom: 8), child: Text(_error!)),
                    Text(
                      _loading
                          ? 'Memuat...'
                          : '${formatCount(_availableIn(null))} dari ${formatCount(_slots.length)} slot kosong',
                      style: theme.textTheme.titleMedium,
                    ),
                    const SizedBox(height: 8),
                    const _Legend(),
                  ],
                ),
              ),
            ),
            SliverToBoxAdapter(
              child: SizedBox(
                height: 48,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  children: [
                    for (final zone in _zones)
                      Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: ChoiceChip(
                          label: Text('Zona $zone · ${_availableIn(zone)}'),
                          selected: zone == _zone,
                          onSelected: (_) => setState(() => _zone = zone),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            SliverPadding(
              padding: const EdgeInsets.all(16),
              sliver: SliverGrid.builder(
                gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                  maxCrossAxisExtent: 64,
                  mainAxisSpacing: 6,
                  crossAxisSpacing: 6,
                  childAspectRatio: 1.4,
                ),
                itemCount: zoneSlots.length,
                itemBuilder: (context, i) => _SlotTile(slot: zoneSlots[i]),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SlotTile extends StatelessWidget {
  const _SlotTile({required this.slot});

  final Slot slot;

  @override
  Widget build(BuildContext context) {
    final label = switch (slot.status) {
      SlotStatus.available => 'kosong',
      SlotStatus.occupied => 'terisi',
      SlotStatus.reserved => 'direservasi',
      SlotStatus.unknown => 'sensor tidak melapor',
    };

    return Semantics(
      label: '${slot.code}, $label',
      child: Container(
        alignment: Alignment.center,
        decoration: BoxDecoration(color: slotColor(slot.status), borderRadius: BorderRadius.circular(6)),
        child: Text(
          slot.shortCode,
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 12),
        ),
      ),
    );
  }
}

class _Legend extends StatelessWidget {
  const _Legend();

  @override
  Widget build(BuildContext context) {
    Widget item(Color color, String text) => Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 12,
              height: 12,
              decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(3)),
            ),
            const SizedBox(width: 6),
            Text(text),
          ],
        );

    return Wrap(
      spacing: 16,
      runSpacing: 4,
      children: [
        item(availableColor, 'Kosong'),
        item(occupiedColor, 'Terisi'),
        item(reservedColor, 'Direservasi'),
        item(unknownColor, 'Sensor tidak melapor'),
      ],
    );
  }
}
