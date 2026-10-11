/// A Philippine Standard Geographic Code (PSGC) place: region, province,
/// city or municipality, or barangay.
class Location {
  const Location({required this.code, required this.name});

  final String code;
  final String name;

  factory Location.fromJson(Map<String, dynamic> json) => Location(
        code: json['code'] as String,
        name: json['name'] as String,
      );
}
