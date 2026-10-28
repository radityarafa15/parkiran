import 'package:shared_preferences/shared_preferences.dart';

/// Kode reservasi yang dibuat dari HP ini, supaya QR-nya bisa dibuka lagi.
class ReservationCodes {
  static const _key = 'reservation_codes';

  Future<List<String>> load() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getStringList(_key) ?? [];
  }

  Future<void> add(String code) async {
    final prefs = await SharedPreferences.getInstance();
    final codes = prefs.getStringList(_key) ?? [];
    // Terbaru di depan; simpan secukupnya.
    await prefs.setStringList(_key, [code, ...codes.where((c) => c != code)].take(20).toList());
  }
}
