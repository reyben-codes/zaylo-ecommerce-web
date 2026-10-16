double parseMoney(Object? value) => (value as num?)?.toDouble() ?? 0;

DateTime? parseDate(Object? value) => value is String ? DateTime.tryParse(value)?.toLocal() : null;
