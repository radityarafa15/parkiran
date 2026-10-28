import 'dart:async';

import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../models.dart';
import '../realtime.dart';
import '../status_colors.dart';
import 'reserve_sheet.dart';

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
                    const SizedBox(height: 4),
                    Text('Ketuk slot hijau untuk reservasi (biaya admin Rp10.000).', style: theme.textTheme.bodySmall),
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
            // Denah skematis: tiap lajur berisi dua baris slot yang saling
            // berhadapan dengan jalur mobil di tengahnya.
            SliverPadding(
              padding: const EdgeInsets.all(16),
              sliver: SliverList.builder(
                itemCount: (zoneSlots.length / (_slotsPerRow * 2)).ceil(),
                itemBuilder: (context, i) => _Lane(
                  slots: zoneSlots.skip(i * _slotsPerRow * 2).take(_slotsPerRow * 2).toList(),
                  onReserve: (slot) => ReserveSheet.show(context, slot: slot, floorName: widget.floor.name),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Jumlah slot per baris pada denah, sama dengan versi web.
const _slotsPerRow = 10;

class _Lane extends StatelessWidget {
  const _Lane({required this.slots, required this.onReserve});

  final List<Slot> slots;
  final void Function(Slot slot) onReserve;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    Widget row(Iterable<Slot> slots) => Row(
      children: [
        for (final slot in slots)
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(2),
              child: AspectRatio(
                aspectRatio: 0.8,
                child: _SlotTile(slot: slot, onTap: slot.status == SlotStatus.available ? () => onReserve(slot) : null),
              ),
            ),
          ),
        // Baris terakhir yang tidak penuh tetap rata dengan baris lain.
        for (var i = slots.length; i < _slotsPerRow; i++) const Expanded(child: SizedBox()),
      ],
    );

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(6),
        child: Column(
          children: [
            row(slots.take(_slotsPerRow)),
            if (slots.length > _slotsPerRow) ...[
              Container(
                margin: const EdgeInsets.symmetric(vertical: 4),
                padding: const EdgeInsets.symmetric(vertical: 2),
                decoration: BoxDecoration(
                  border: Border.symmetric(horizontal: BorderSide(color: theme.dividerColor, width: 1.5)),
                ),
                alignment: Alignment.center,
                child: Text('jalur →', style: theme.textTheme.labelSmall?.copyWith(color: theme.hintColor)),
              ),
              row(slots.skip(_slotsPerRow)),
            ],
          ],
        ),
      ),
    );
  }
}

class _SlotTile extends StatelessWidget {
  const _SlotTile({required this.slot, this.onTap});

  final Slot slot;

  /// Hanya slot kosong yang bisa diketuk untuk reservasi.
  final VoidCallback? onTap;

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
      button: onTap != null,
      container: true,
      excludeSemantics: true,
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          alignment: Alignment.center,
          decoration: BoxDecoration(color: slotColor(slot.status), borderRadius: BorderRadius.circular(6)),
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              slot.number,
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 12),
            ),
          ),
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
