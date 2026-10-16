/// Formats pesos the way the web store does: ₱1,299.00
String peso(double amount, {bool cents = true}) {
  final fixed = amount.abs().toStringAsFixed(cents ? 2 : 0);
  final parts = fixed.split('.');
  final whole = parts.first.replaceAllMapped(RegExp(r'\B(?=(\d{3})+(?!\d))'), (_) => ',');
  return '${amount < 0 ? '-' : ''}₱$whole${parts.length > 1 ? '.${parts[1]}' : ''}';
}

const _months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

String shortDate(DateTime? date) =>
    date == null ? '' : '${_months[date.month - 1]} ${date.day}, ${date.year}';

String dateTimeLabel(DateTime? date) {
  if (date == null) return '';
  final hour = date.hour % 12 == 0 ? 12 : date.hour % 12;
  final minute = date.minute.toString().padLeft(2, '0');
  return '${shortDate(date)} · $hour:$minute ${date.hour < 12 ? 'AM' : 'PM'}';
}

String relativeTime(DateTime? date) {
  if (date == null) return '';
  final diff = DateTime.now().difference(date);
  if (diff.inMinutes < 1) return 'Just now';
  if (diff.inHours < 1) return '${diff.inMinutes}m ago';
  if (diff.inDays < 1) return '${diff.inHours}h ago';
  if (diff.inDays < 7) return '${diff.inDays}d ago';
  return shortDate(date);
}

/// `YYYY-MM-DD`, the date format every API date field expects.
String apiDate(DateTime date) => '${date.year.toString().padLeft(4, '0')}-'
    '${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
