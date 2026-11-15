import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/state/app_state.dart';

Future<void> openGatewayPayment(BuildContext context, int orderId) async {
  try {
    final api = AppScope.read(context).api;
    final url = Uri.parse(await api.gatewayCheckout(orderId));
    if (!isAllowedPaymentUrl(url, Uri.parse(api.baseUrl)) ||
        !await launchUrl(url, mode: LaunchMode.externalApplication)) {
      throw const ApiException('Could not open secure checkout. Retry from To Pay in My Orders.');
    }
  } on ApiException catch (error) {
    if (context.mounted) showMessage(context, error.message);
  } catch (_) {
    if (context.mounted) showMessage(context, 'Could not open payment. Your order is saved under To Pay.');
  }
}

bool isAllowedPaymentUrl(Uri url, Uri apiBase) {
  if (url.userInfo.isNotEmpty) return false;
  if (url.scheme == 'https' && url.host == 'checkout.paymongo.com' && url.port == 443) return true;
  final localCheckout = url.origin == apiBase.origin &&
      (url.scheme == 'https' || (kDebugMode && url.scheme == 'http')) &&
      RegExp(r'^/payments/checkout/\d+$').hasMatch(url.path) &&
      url.queryParameters['signature']?.isNotEmpty == true &&
      int.tryParse(url.queryParameters['expires'] ?? '') != null;
  return localCheckout;
}
