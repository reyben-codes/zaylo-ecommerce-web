import 'package:zaylo_mobile/models/json_parsing.dart';
import 'package:zaylo_mobile/models/support_models.dart';

class Category {
  const Category({required this.id, required this.label, required this.imageUrl});

  final String id;
  final String label;
  final String imageUrl;

  factory Category.fromJson(Map<String, dynamic> json) => Category(
        id: json['id'] as String,
        label: json['label'] as String,
        imageUrl: json['image'] as String,
      );
}

class ProductVariant {
  const ProductVariant({
    required this.id,
    required this.name,
    required this.price,
    required this.stock,
  });

  final int id;
  final String name;
  final double price;
  final int stock;

  bool get inStock => stock > 0;

  factory ProductVariant.fromJson(Map<String, dynamic> json) => ProductVariant(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String,
        price: (json['price'] as num).toDouble(),
        stock: (json['stock'] as num).toInt(),
      );
}

class Product {
  const Product({
    required this.id,
    required this.slug,
    required this.name,
    required this.categoryLabel,
    required this.price,
    this.category,
    this.originalPrice,
    this.badge,
    this.imageUrl,
    this.description,
    this.hasOptions = false,
    this.saved = false,
    this.rating,
    this.reviewCount = 0,
    this.stock,
    this.sellerName,
    this.images = const [],
    this.variants = const [],
  });

  final int id;
  final String slug;
  final String name;
  final String categoryLabel;
  final String? category;
  final double price;
  final double? originalPrice;
  final String? badge;
  final String? imageUrl;
  final String? description;
  final bool hasOptions;
  final bool saved;

  /// Average stars (1 decimal) from verified purchases; null without reviews.
  final double? rating;
  final int reviewCount;

  /// How many can be bought across its active options; null from older servers.
  final int? stock;

  /// Detail-only fields; empty or null on list payloads.
  final String? sellerName;
  final List<String> images;
  final List<ProductVariant> variants;

  /// Whole-number percentage saved versus the original price, if on sale.
  int? get discountPercent {
    final original = originalPrice;
    if (original == null || original <= price) return null;
    return ((original - price) / original * 100).round();
  }

  factory Product.fromJson(Map<String, dynamic> json) => Product(
        id: (json['id'] as num).toInt(),
        slug: json['slug'] as String,
        name: json['name'] as String,
        categoryLabel: json['category_label'] as String? ?? 'Marketplace',
        category: json['category'] as String?,
        price: (json['price'] as num).toDouble(),
        originalPrice: (json['original_price'] as num?)?.toDouble(),
        badge: json['badge'] as String?,
        imageUrl: json['image_url'] as String?,
        description: json['description'] as String?,
        hasOptions: json['has_options'] as bool? ?? false,
        saved: json['saved'] as bool? ?? false,
        rating: (json['rating'] as num?)?.toDouble(),
        reviewCount: (json['review_count'] as num?)?.toInt() ?? 0,
        stock: (json['stock'] as num?)?.toInt(),
        sellerName: json['seller_name'] as String?,
        images: List<String>.from(json['images'] as List<dynamic>? ?? const []),
        variants: (json['variants'] as List<dynamic>? ?? const [])
            .map((item) => ProductVariant.fromJson(item as Map<String, dynamic>))
            .toList(),
      );
}

class ProductPage {
  const ProductPage({
    required this.products,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  final List<Product> products;
  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;

  factory ProductPage.fromJson(Map<String, dynamic> json) => ProductPage(
        products: (json['data'] as List<dynamic>)
            .map((item) => Product.fromJson(item as Map<String, dynamic>))
            .toList(),
        currentPage: (json['current_page'] as num).toInt(),
        lastPage: (json['last_page'] as num).toInt(),
        total: (json['total'] as num).toInt(),
      );
}

class StorefrontHome {
  const StorefrontHome({
    required this.categories,
    required this.heroImages,
    required this.newArrivals,
    this.flashSales = const [],
    this.suggested = const [],
    this.announcements = const [],
  });

  final List<Category> categories;
  final List<String> heroImages;
  final List<Product> newArrivals;

  /// Signed-in only, like the web buyer dashboard.
  final List<Product> flashSales;
  final List<Product> suggested;

  /// Live notices from the ZAYLO admin for buyers (or everyone, for guests).
  final List<Announcement> announcements;

  static List<Product> _products(Object? list) => (list as List<dynamic>? ?? const [])
      .map((item) => Product.fromJson(item as Map<String, dynamic>))
      .toList();

  factory StorefrontHome.fromJson(Map<String, dynamic> json) => StorefrontHome(
        categories: (json['categories'] as List<dynamic>)
            .map((item) => Category.fromJson(item as Map<String, dynamic>))
            .toList(),
        heroImages: List<String>.from(json['hero_images'] as List<dynamic>),
        newArrivals: _products(json['new_arrivals']),
        flashSales: _products(json['flash_sales']),
        suggested: _products(json['suggested']),
        announcements: Announcement.listFrom(json['announcements']),
      );
}

/// A verified-purchase review; buyers are shown by first name only.
class ProductReview {
  const ProductReview({
    required this.id,
    required this.rating,
    required this.buyerName,
    this.comment,
    this.photos = const [],
    this.createdAt,
  });

  final int id;
  final int rating;
  final String buyerName;
  final String? comment;
  final List<String> photos;
  final DateTime? createdAt;

  factory ProductReview.fromJson(Map<String, dynamic> json) => ProductReview(
        id: (json['id'] as num).toInt(),
        rating: (json['rating'] as num).toInt(),
        buyerName: json['buyer_name'] as String? ?? 'ZAYLO buyer',
        comment: json['comment'] as String?,
        photos: List<String>.from(json['photos'] as List<dynamic>? ?? const []),
        createdAt: parseDate(json['created_at']),
      );
}

/// GET /mobile/products/{slug}/reviews: the summary plus one page of reviews.
class ProductReviewPage {
  const ProductReviewPage({
    required this.count,
    required this.breakdown,
    required this.reviews,
    required this.currentPage,
    required this.lastPage,
    this.average,
  });

  final double? average;
  final int count;

  /// Reviews per star rating, 5 down to 1.
  final Map<int, int> breakdown;
  final List<ProductReview> reviews;
  final int currentPage;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;

  factory ProductReviewPage.fromJson(Map<String, dynamic> json) => ProductReviewPage(
        average: (json['average'] as num?)?.toDouble(),
        count: (json['count'] as num?)?.toInt() ?? 0,
        breakdown: (json['breakdown'] as Map<String, dynamic>? ?? const {})
            .map((stars, total) => MapEntry(int.parse(stars), (total as num).toInt())),
        reviews: (json['reviews'] as List<dynamic>? ?? const [])
            .map((item) => ProductReview.fromJson(item as Map<String, dynamic>))
            .toList(),
        currentPage: (json['page'] as num?)?.toInt() ?? 1,
        lastPage: (json['last_page'] as num?)?.toInt() ?? 1,
      );
}
