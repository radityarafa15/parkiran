import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:parkiran/main.dart';
import 'package:parkiran/src/api.dart';

void main() {
  final api = ParkirApi('http://parkir.test', client: MockClient((request) async {
    final body = switch (request.url.path) {
      '/api/realtime' => {'data': {'key': null, 'host': 'localhost', 'port': 8080, 'scheme': 'http'}},
      '/api/floors' => {
          'data': [
            {'id': 1, 'name': 'Lantai P1', 'level': 1, 'total': 2332, 'available': 940, 'occupied': 1392, 'unknown': 0},
            {'id': 2, 'name': 'Lantai P2', 'level': 2, 'total': 2332, 'available': 0, 'occupied': 2332, 'unknown': 0},
          ],
        },
      '/api/floors/1/slots' => {
          'data': [
            {'id': 1, 'code': 'P1-A001', 'zone': 'A', 'status': 'available'},
            {'id': 2, 'code': 'P1-A002', 'zone': 'A', 'status': 'occupied'},
            {'id': 3, 'code': 'P1-B001', 'zone': 'B', 'status': 'available'},
          ],
        },
      _ => null,
    };
    return body == null ? http.Response('', 404) : http.Response(jsonEncode(body), 200);
  }));

  testWidgets('shows floors, then a floor\'s slots by zone', (tester) async {
    await tester.pumpWidget(ParkiranApp(api: api));
    await tester.pumpAndSettle();

    expect(find.text('Lantai P1'), findsOneWidget);
    expect(find.text('940'), findsOneWidget);
    expect(find.text('Penuh'), findsOneWidget);

    await tester.tap(find.text('Lantai P1'));
    await tester.pumpAndSettle();

    expect(find.text('2 dari 3 slot kosong'), findsOneWidget);
    expect(find.text('Zona A · 1'), findsOneWidget);
    expect(find.text('A001'), findsOneWidget);
    expect(find.text('B001'), findsNothing);

    await tester.tap(find.text('Zona B · 1'));
    await tester.pumpAndSettle();
    expect(find.text('B001'), findsOneWidget);

    // Hentikan timer refresh berkala sebelum tes selesai.
    await tester.pumpWidget(const SizedBox());
  });
}
