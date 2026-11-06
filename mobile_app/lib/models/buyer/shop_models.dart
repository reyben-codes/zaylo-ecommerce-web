/// A category on a shop's Categories tab.
class ShopCategory {
  const ShopCategory({required this.id, required this.label, required this.count});

  final String id;
  final String label;
  final int count;

  factory ShopCategory.fromJson(Map<String, dynamic> json) => ShopCategory(
        id: json['id'] as String,
        label: json['label'] as String,
        count: (json['count'] as num?)?.toInt() ?? 0,
      );
}

/// One of the seller's own groupings on their shop page, such as "New Arrivals".
class ShopSection {
  const ShopSection({required this.id, required this.name, required this.count});

  final int id;
  final String name;
  final int count;

  factory ShopSection.fromJson(Map<String, dynamic> json) => ShopSection(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String,
        count: (json['count'] as num?)?.toInt() ?? 0,
      );
}

/// A seller's public shop. [categories] and [sections] only come with GET /mobile/shops/{id}.
class Shop {
  const Shop({
    required this.id,
    required this.name,
    this.description,
    this.location,
    this.avatarUrl,
    this.coverUrl,
    this.productCount = 0,
    this.followers = 0,
    this.rating,
    this.reviewCount = 0,
    this.sold = 0,
    this.following = false,
    this.categories = const [],
    this.sections = const [],
  });

  final int id;
  final String name;
  final String? description;
  final String? location;
  final String? avatarUrl;
  final String? coverUrl;
  final int productCount;
  final int followers;

  /// Average stars across the shop's products; null without reviews.
  final double? rating;
  final int reviewCount;

  /// Units delivered to buyers.
  final int sold;
  final bool following;
  final List<ShopCategory> categories;

  /// The seller's own sections, in their order; empty when they made none.
  final List<ShopSection> sections;

  /// Up to two letters for a shop without a photo.
  String get initials {
    final words = name.trim().split(RegExp(r'\s+')).where((word) => word.isNotEmpty).take(2);
    final letters = words.map((word) => word.substring(0, 1).toUpperCase()).join();
    return letters.isEmpty ? 'Z' : letters;
  }

  Shop copyWith({bool? following, int? followers}) => Shop(
        id: id,
        name: name,
        description: description,
        location: location,
        avatarUrl: avatarUrl,
        coverUrl: coverUrl,
        productCount: productCount,
        followers: followers ?? this.followers,
        rating: rating,
        reviewCount: reviewCount,
        sold: sold,
        following: following ?? this.following,
        categories: categories,
        sections: sections,
      );

  factory Shop.fromJson(Map<String, dynamic> json) => Shop(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String,
        description: json['description'] as String?,
        location: json['location'] as String?,
        avatarUrl: json['avatar_url'] as String?,
        coverUrl: json['cover_url'] as String?,
        productCount: (json['product_count'] as num?)?.toInt() ?? 0,
        followers: (json['followers'] as num?)?.toInt() ?? 0,
        rating: (json['rating'] as num?)?.toDouble(),
        reviewCount: (json['review_count'] as num?)?.toInt() ?? 0,
        sold: (json['sold'] as num?)?.toInt() ?? 0,
        following: json['following'] as bool? ?? false,
        categories: (json['categories'] as List<dynamic>? ?? const [])
            .map((item) => ShopCategory.fromJson(item as Map<String, dynamic>))
            .toList(),
        sections: (json['sections'] as List<dynamic>? ?? const [])
            .map((item) => ShopSection.fromJson(item as Map<String, dynamic>))
            .toList(),
      );
}
