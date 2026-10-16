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

  /// Detail-only fields; empty or null on list payloads.
  final int? stock;
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
  });

  final List<Category> categories;
  final List<String> heroImages;
  final List<Product> newArrivals;

  /// Signed-in only, like the web buyer dashboard.
  final List<Product> flashSales;
  final List<Product> suggested;

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
      );
}
