import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/features/buyer/orders/gateway_payment.dart';

void main() {
  final base = Uri.parse('https://zaylo-zyl.shop/api');
  test('accepts provider checkout and signed checkout on the app server', () {
    expect(isAllowedPaymentUrl(Uri.parse('https://checkout.paymongo.com/cs_test'), base), isTrue);
    expect(isAllowedPaymentUrl(Uri.parse('https://zaylo-zyl.shop/payments/checkout/42?expires=1800000000&signature=abc'), base), isTrue);
  });
  test('rejects arbitrary same-origin pages, unsigned pages and lookalike hosts', () {
    for (final value in [
      'https://other.example/payments/checkout/42?expires=1800000000&signature=abc',
      'https://zaylo-zyl.shop/admin?expires=1800000000&signature=abc',
      'https://zaylo-zyl.shop/payments/checkout/42',
      'https://checkout.paymongo.com.evil.example/cs_test',
      'https://attacker@checkout.paymongo.com/cs_test',
      'https://zaylo-zyl.shop/payments/checkout/42?expires=invalid&signature=abc',
    ]) { expect(isAllowedPaymentUrl(Uri.parse(value), base), isFalse, reason: value); }
  });
}
