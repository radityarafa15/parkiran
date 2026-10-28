import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:parkiran/main.dart';
import 'package:parkiran/src/api.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';

Map<String, Object?> reservationJson(String status, {String label = 'Menunggu pembayaran'}) {
  final now = DateTime.now().toUtc();
  return {
    'code': 'RSVABCD2345',
    'status': status,
    'status_label': label,
    'name': 'Rafa',
    'plate_number': 'B 1234 XYZ',
    'fee': 10000,
    'slot': {'id': 1, 'code': 'P1-A001', 'floor': 'Lantai P1'},
    'pay_before': now.add(const Duration(minutes: 15)).toIso8601String(),
    'paid_at': status == 'pending' ? null : now.toIso8601String(),
    'expires_at': status == 'pending' ? null : now.add(const Duration(hours: 2)).toIso8601String(),
    'checked_in_at': null,
    'checked_out_at': null,
    'parked_minutes': null,
    'parked_hours': null,
    'qr_svg_url': 'http://parkir.test/reservasi/RSVABCD2345/qr.svg',
  };
}

void main() {
  late List<http.Request> posts;
  late String status;

  ParkirApi api({bool slotTaken = false}) => ParkirApi(
    'http://parkir.test',
    client: MockClient((request) async {
      if (request.method == 'POST') posts.add(request);
      final path = request.url.path;
      Object? body;
      var code = 200;

      if (path == '/api/realtime') {
        body = {
          'data': {'key': null, 'host': 'localhost', 'port': 8080, 'scheme': 'http'},
        };
      } else if (path == '/api/floors') {
        body = {
          'data': [
            {
              'id': 1,
              'name': 'Lantai P1',
              'level': 1,
              'total': 2,
              'available': 1,
              'occupied': 1,
              'reserved': 0,
              'unknown': 0,
            },
          ],
        };
      } else if (path == '/api/floors/1/slots') {
        body = {
          'data': [
            {'id': 1, 'code': 'P1-A001', 'zone': 'A', 'status': 'available'},
            {'id': 2, 'code': 'P1-A002', 'zone': 'A', 'status': 'occupied'},
          ],
        };
      } else if (path == '/api/reservations' && slotTaken) {
        code = 409;
        body = {'message': 'Slot P1-A001 sudah tidak tersedia. Silakan pilih slot lain.'};
      } else if (path == '/api/reservations') {
        status = 'pending';
        code = 201;
        body = {'data': reservationJson(status)};
      } else if (path == '/api/reservations/RSVABCD2345/pay') {
        status = 'paid';
        body = {'data': reservationJson(status, label: 'Menunggu masuk gerbang')};
      } else if (path == '/api/reservations/RSVABCD2345') {
        body = {
          'data': reservationJson(status, label: status == 'paid' ? 'Menunggu masuk gerbang' : 'Menunggu pembayaran'),
        };
      }

      return body == null ? http.Response('', 404) : http.Response(jsonEncode(body), code);
    }),
  );

  setUp(() {
    posts = [];
    status = 'pending';
    SharedPreferences.setMockInitialValues({});
  });

  Future<void> openReserveSheet(WidgetTester tester, ParkirApi api) async {
    await tester.pumpWidget(ParkiranApp(api: api));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Lantai P1'));
    await tester.pumpAndSettle();

    // Slot terisi tidak bisa diketuk.
    await tester.tap(find.text('002'));
    await tester.pumpAndSettle();
    expect(find.text('Reservasi slot P1-A002'), findsNothing);

    await tester.tap(find.text('001'));
    await tester.pumpAndSettle();
    expect(find.text('Reservasi slot P1-A001'), findsOneWidget);
  }

  testWidgets('reserves a free slot, pays, and shows the gate QR', (tester) async {
    await openReserveSheet(tester, api());

    await tester.tap(find.widgetWithText(FilledButton, 'Reservasi'));
    await tester.pump();
    expect(find.text('Nama wajib diisi.'), findsOneWidget);
    expect(posts, isEmpty);

    await tester.enterText(find.widgetWithText(TextFormField, 'Nama'), 'Rafa');
    await tester.enterText(find.widgetWithText(TextFormField, 'Plat nomor'), 'B 1234 XYZ');
    await tester.tap(find.widgetWithText(FilledButton, 'Reservasi'));
    await tester.pumpAndSettle();

    expect(jsonDecode(posts.single.body), {'slot_id': 1, 'name': 'Rafa', 'plate_number': 'B 1234 XYZ'});
    expect(find.text('Menunggu pembayaran'), findsOneWidget);
    expect(find.text('Sisa waktu bayar'), findsOneWidget);
    expect(find.byType(QrImageView), findsNothing);

    await tester.tap(find.text('Bayar Rp10.000'));
    await tester.pumpAndSettle();

    expect(find.text('Menunggu masuk gerbang'), findsOneWidget);
    expect(find.text('Sisa waktu masuk'), findsOneWidget);
    expect(find.byType(QrImageView), findsOneWidget);
    expect(find.text('RSVABCD2345'), findsWidgets);

    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getStringList('reservation_codes'), ['RSVABCD2345']);

    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('shows the server message when the slot was just taken', (tester) async {
    await openReserveSheet(tester, api(slotTaken: true));

    await tester.enterText(find.widgetWithText(TextFormField, 'Nama'), 'Rafa');
    await tester.enterText(find.widgetWithText(TextFormField, 'Plat nomor'), 'B 1234 XYZ');
    await tester.tap(find.widgetWithText(FilledButton, 'Reservasi'));
    await tester.pumpAndSettle();

    expect(find.text('Slot P1-A001 sudah tidak tersedia. Silakan pilih slot lain.'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('lists reservations saved on this phone', (tester) async {
    SharedPreferences.setMockInitialValues({
      'reservation_codes': ['RSVABCD2345'],
    });
    status = 'paid';

    await tester.pumpWidget(ParkiranApp(api: api()));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Reservasi saya'));
    await tester.pumpAndSettle();

    expect(find.text('P1-A001 · Lantai P1'), findsOneWidget);
    expect(find.text('B 1234 XYZ · Menunggu masuk gerbang'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
  });
}
