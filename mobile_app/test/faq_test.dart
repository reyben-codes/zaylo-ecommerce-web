import 'package:flutter_test/flutter_test.dart';
import 'package:zaylo_mobile/models/support_models.dart';

void main() {
  final faq = FaqData.fromJson({
    'greeting': 'Hi!',
    'items': [
      {
        'q': 'How do I return an item or get a refund?',
        'a': 'File a complaint from the order.',
        'keywords': ['return', 'refund', 'money back'],
      },
      {
        'q': 'How much is shipping?',
        'a': 'Shipping is ₱120 for each shop.',
        'keywords': ['shipping', 'shipping fee'],
      },
    ],
  });

  test('a typed question finds the answer by its keywords', () {
    final ranked = faq.match('I want my money back please');
    expect(ranked.first.$1.answer, 'File a complaint from the order.');
    expect(ranked.first.$2, greaterThanOrEqualTo(3));
  });

  test('phrases count more than single words, and punctuation is ignored', () {
    expect(faq.match('Shipping fee?').first.$2, greaterThan(faq.match('shipping').first.$2));
  });

  test('an unrelated question matches nothing', () {
    expect(faq.match('Do you sell cars?'), isEmpty);
  });
}
