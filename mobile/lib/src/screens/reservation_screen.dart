import 'dart:async';

import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../app_scope.dart';
import '../models.dart';
import '../status_colors.dart';

/// Satu reservasi: bayar, tunjukkan QR di gerbang, lalu lihat lama parkir.
class ReservationScreen extends StatefulWidget {
  const ReservationScreen({super.key, required this.code, this.initial});

  final String code;
  final Reservation? initial;

  @override
  State<ReservationScreen> createState() => _ReservationScreenState();
}

class _ReservationScreenState extends State<ReservationScreen> {
  Reservation? _reservation;
  String? _error;
  bool _paying = false;
  Timer? _clock;
  Timer? _refresh;

  @override
  void initState() {
    super.initState();
    _reservation = widget.initial;
    // Hitung mundur tiap detik; status (misalnya sudah di-scan petugas) tiap 30 detik.
    _clock = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
    _refresh = Timer.periodic(const Duration(seconds: 30), (_) {
      if (_reservation?.status.isActive ?? true) _load();
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_reservation == null && _error == null) _load();
  }

  @override
  void dispose() {
    _clock?.cancel();
    _refresh?.cancel();
    super.dispose();
  }

  void _tick() {
    final deadline = _reservation?.deadline;
    if (deadline != null && !DateTime.now().isBefore(deadline)) {
      _load();
    } else if (mounted) {
      setState(() {});
    }
  }

  Future<void> _load() async {
    try {
      final reservation = await AppScope.of(context).api.reservation(widget.code);
      if (mounted) setState(() => (_reservation = reservation, _error = null));
    } catch (e) {
      if (mounted) setState(() => _error = 'Gagal memuat reservasi. Tarik ke bawah untuk mencoba lagi.');
    }
  }

  Future<void> _pay() async {
    setState(() => _paying = true);
    try {
      final reservation = await AppScope.of(context).api.payReservation(widget.code);
      if (mounted) setState(() => (_reservation = reservation, _error = null));
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
      await _load();
    } finally {
      if (mounted) setState(() => _paying = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final r = _reservation;

    return Scaffold(
      appBar: AppBar(title: Text(r == null ? 'Reservasi' : 'Reservasi ${r.slotCode}')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
            if (r == null && _error == null) const Center(child: CircularProgressIndicator()),
            if (r != null) ...[
              Align(
                alignment: Alignment.centerLeft,
                child: _StatusBadge(reservation: r),
              ),
              const SizedBox(height: 12),
              Card(
                child: Padding(padding: const EdgeInsets.all(16), child: _body(context, r)),
              ),
              const SizedBox(height: 12),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: _Facts(reservation: r),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Siapa pun yang memegang QR atau kode ini bisa memakai reservasimu.',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _body(BuildContext context, Reservation r) {
    final theme = Theme.of(context);
    final now = DateTime.now();

    Widget countdown(String label, DateTime deadline) {
      final left = deadline.difference(now);
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label),
          Text(
            formatClock(left),
            style: theme.textTheme.displaySmall?.copyWith(
              fontFeatures: const [FontFeature.tabularFigures()],
              color: left.inMinutes < 10 ? occupiedColor : null,
            ),
          ),
        ],
      );
    }

    Widget qr() => Column(
      children: [
        const SizedBox(height: 12),
        Container(
          color: Colors.white,
          padding: const EdgeInsets.all(8),
          child: QrImageView(data: r.code, size: 220, backgroundColor: Colors.white),
        ),
        const SizedBox(height: 8),
        SelectableText(
          r.code,
          style: theme.textTheme.titleLarge?.copyWith(letterSpacing: 2, fontWeight: FontWeight.bold),
        ),
      ],
    );

    return switch (r.status) {
      ReservationStatus.pending => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          countdown('Sisa waktu bayar', r.payBefore),
          const SizedBox(height: 8),
          Text('Bayar sebelum ${formatTime(r.payBefore)}. Slot ditahan untukmu sampai saat itu.'),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _paying ? null : _pay,
            child: Text(_paying ? 'Memproses...' : 'Bayar ${formatRupiah(r.fee)}'),
          ),
          const SizedBox(height: 4),
          Text('Pembayaran masih simulasi: belum ada uang yang ditarik.', style: theme.textTheme.bodySmall),
        ],
      ),
      ReservationStatus.paid => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          countdown('Sisa waktu masuk', r.expiresAt!),
          const SizedBox(height: 8),
          Text(
            'Tunjukkan QR ini di gerbang masuk sebelum ${formatTime(r.expiresAt!)}. Lewat dari itu reservasi hangus.',
          ),
          Center(child: qr()),
        ],
      ),
      ReservationStatus.parked => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Lama parkir'),
          Text(
            formatClock(now.difference(r.checkedInAt!)),
            style: theme.textTheme.displaySmall?.copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
          ),
          const SizedBox(height: 8),
          Text('Jam parkir berjalan sejak ${formatTime(r.checkedInAt!)}. Scan QR yang sama di gerbang keluar.'),
          Center(child: qr()),
        ],
      ),
      ReservationStatus.done => Text(
        'Parkir ${formatMinutes(r.parkedMinutes ?? 0)} (${r.parkedHours ?? 1} jam dihitung). Terima kasih.',
      ),
      ReservationStatus.expired => Text(
        r.paidAt != null
            ? 'QR tidak di-scan di gerbang masuk sebelum batas waktunya, jadi reservasi hangus dan biaya admin tidak dikembalikan.'
            : 'Biaya admin tidak dibayar tepat waktu, jadi slotnya dilepas.',
      ),
    };
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.reservation});

  final Reservation reservation;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: reservationColor(reservation.status), borderRadius: BorderRadius.circular(999)),
      child: Text(
        reservation.statusLabel,
        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
      ),
    );
  }
}

class _Facts extends StatelessWidget {
  const _Facts({required this.reservation});

  final Reservation reservation;

  @override
  Widget build(BuildContext context) {
    final r = reservation;
    final muted = Theme.of(context).textTheme.bodyMedium?.copyWith(color: Theme.of(context).hintColor);

    TableRow row(String label, String value) => TableRow(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 3),
          child: Text(label, style: muted),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 3),
          child: Text(value, style: const TextStyle(fontWeight: FontWeight.w600)),
        ),
      ],
    );

    return Table(
      columnWidths: const {0: IntrinsicColumnWidth(), 1: FlexColumnWidth()},
      defaultVerticalAlignment: TableCellVerticalAlignment.middle,
      children: [
        row('Slot  ', '${r.slotCode} · ${r.floorName}'),
        row('Nama  ', r.name),
        row('Plat nomor  ', r.plateNumber),
        row('Biaya admin  ', '${formatRupiah(r.fee)}${r.paidAt != null ? ' · lunas' : ''}'),
        row('Kode  ', r.code),
      ],
    );
  }
}
