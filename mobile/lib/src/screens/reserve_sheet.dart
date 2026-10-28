import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../models.dart';
import 'reservation_screen.dart';

/// Formulir reservasi untuk satu slot kosong, tampil dari bawah layar.
class ReserveSheet extends StatefulWidget {
  const ReserveSheet({super.key, required this.slot, required this.floorName});

  final Slot slot;
  final String floorName;

  static Future<void> show(BuildContext context, {required Slot slot, required String floorName}) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => ReserveSheet(slot: slot, floorName: floorName),
    );
  }

  @override
  State<ReserveSheet> createState() => _ReserveSheetState();
}

class _ReserveSheetState extends State<ReserveSheet> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _plate = TextEditingController();
  bool _sending = false;
  String? _error;

  static final _platePattern = RegExp(r'^[A-Za-z]{1,2}\s*\d{1,4}\s*[A-Za-z]{0,3}$');

  @override
  void dispose() {
    _name.dispose();
    _plate.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() => (_sending = true, _error = null));

    final scope = AppScope.of(context);
    final navigator = Navigator.of(context);
    try {
      final reservation = await scope.api.createReservation(
        slotId: widget.slot.id,
        name: _name.text.trim(),
        plateNumber: _plate.text.trim(),
      );
      await scope.reservationCodes.add(reservation.code);
      navigator
        ..pop()
        ..push(
          MaterialPageRoute(
            builder: (_) => ReservationScreen(code: reservation.code, initial: reservation),
          ),
        );
    } catch (e) {
      if (mounted) setState(() => (_sending = false, _error = e.toString()));
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.viewInsetsOf(context).bottom),
      child: Form(
        key: _form,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Reservasi slot ${widget.slot.code}', style: theme.textTheme.titleLarge),
            Text('${widget.floorName} · Zona ${widget.slot.zone}', style: theme.textTheme.bodyMedium),
            const SizedBox(height: 12),
            Text(
              'Biaya admin Rp10.000, dibayar dalam 15 menit. Setelah dibayar kamu mendapat QR untuk gerbang masuk. '
              'Jika belum masuk dalam 2 jam, reservasi hangus dan biaya tidak dikembalikan.',
              style: theme.textTheme.bodySmall,
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'Nama', border: OutlineInputBorder()),
              textCapitalization: TextCapitalization.words,
              maxLength: 60,
              validator: (v) => (v ?? '').trim().isEmpty ? 'Nama wajib diisi.' : null,
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: _plate,
              decoration: const InputDecoration(
                labelText: 'Plat nomor',
                hintText: 'B 1234 XYZ',
                border: OutlineInputBorder(),
              ),
              textCapitalization: TextCapitalization.characters,
              maxLength: 16,
              validator: (v) => _platePattern.hasMatch((v ?? '').trim())
                  ? null
                  : 'Format plat nomor tidak dikenali, contoh: B 1234 XYZ.',
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Text(_error!, style: TextStyle(color: theme.colorScheme.error)),
              ),
            const SizedBox(height: 8),
            FilledButton(onPressed: _sending ? null : _submit, child: Text(_sending ? 'Memproses...' : 'Reservasi')),
          ],
        ),
      ),
    );
  }
}
