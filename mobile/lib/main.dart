import 'package:flutter/material.dart';

import 'src/api.dart';
import 'src/app_scope.dart';
import 'src/screens/floor_list_screen.dart';

/// Alamat backend Laravel. Emulator Android menjangkau komputer lewat 10.0.2.2.
/// Contoh: flutter run --dart-define=API_BASE_URL=https://parkir.mall-anda.id
const apiBaseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8000');

void main() {
  runApp(ParkiranApp(api: ParkirApi(apiBaseUrl)));
}

class ParkiranApp extends StatelessWidget {
  const ParkiranApp({super.key, required this.api});

  final ParkirApi api;

  @override
  Widget build(BuildContext context) {
    return AppScope(
      api: api,
      child: MaterialApp(
        title: 'Parkiran',
        theme: ThemeData(colorSchemeSeed: const Color(0xFF1F9D55), useMaterial3: true),
        darkTheme: ThemeData(
          colorSchemeSeed: const Color(0xFF1F9D55),
          brightness: Brightness.dark,
          useMaterial3: true,
        ),
        home: const FloorListScreen(),
      ),
    );
  }
}
