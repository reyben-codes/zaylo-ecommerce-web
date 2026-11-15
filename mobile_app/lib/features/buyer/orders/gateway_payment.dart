import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/state/app_state.dart';

Future<void> openGatewayPayment(BuildContext context, int orderId) async {
  try {
    final url = Uri.parse(await AppScope.read(context).api.gatewayCheckout(orderId));
    if (url.scheme != 'https' || url.host != 'checkout.paymongo.com' ||
        !await launchUrl(url, mode: LaunchMode.externalApplication)) {
      throw const ApiException('Could not open secure checkout. Retry from To Pay in My Orders.');
    }
  } on ApiException catch (error) {
    if (context.mounted) showMessage(context, error.message);
  } catch (_) {
    if (context.mounted) showMessage(context, 'Could not open payment. Your order is saved under To Pay.');
  }
}
