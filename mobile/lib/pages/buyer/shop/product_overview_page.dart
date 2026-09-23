import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../../services/api_service.dart';

class ProductOverviewPage extends StatefulWidget {
  final int productId;
  final String token;

  const ProductOverviewPage({
    super.key,
    required this.productId,
    required this.token,
  });

  @override
  State<ProductOverviewPage> createState() =>
      _ProductOverviewPageState();
}

class _ProductOverviewPageState
    extends State<ProductOverviewPage> {
  static const background = Color(0xFFFAF7F2);
  static const dark = Color(0xFF1A1714);
  static const secondaryText = Color(0xFF6B5F54);
  static const border = Color(0xFFE5DFD8);

  bool _isLoading = true;
  String? _errorMessage;
  Map<String, dynamic>? _product;

  @override
  void initState() {
    super.initState();
    _loadProduct();
  }

  Future<void> _loadProduct() async {
    try {
      final data = await ApiService.getProduct(
        productId: widget.productId,
        token: widget.token,
      );

      if (!mounted) return;

      setState(() {
        _product = data['product'] as Map<String, dynamic>?;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _errorMessage =
            e.toString().replaceFirst('Exception: ', '');
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: background,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        surfaceTintColor: Colors.white,
        leading: IconButton(
          onPressed: () {
            Navigator.pop(context);
          },
          icon: const Icon(
            Icons.arrow_back,
            color: dark,
          ),
        ),
        centerTitle: true,
        title: Image.asset(
          'assets/images/ZAYLO_LOGO_DARK.png',
          height: 34,
        ),
        actions: [
          IconButton(
            onPressed: () {},
            icon: const Icon(
              Icons.favorite_border,
              color: dark,
            ),
          ),
          IconButton(
            onPressed: () {},
            icon: const Icon(
              Icons.shopping_bag_outlined,
              color: dark,
            ),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(
                color: dark,
              ),
            )
          : _errorMessage != null
              ? _buildError()
              : _product == null
                  ? _buildError(
                      message: 'Product not found.',
                    )
                  : _buildProduct(),
    );
  }

  Widget _buildProduct() {
    final product = _product!;

    final name = product['name']?.toString() ?? 'Product';
    final description =
        product['description']?.toString() ?? '';
    final price = product['price']?.toString() ?? '0.00';
    final originalPrice =
        product['original_price']?.toString();
    final imageUrl = product['image_url']?.toString();
    final category =
        product['category']?.toString() ?? '';
    final fashionCategory =
        product['fashion_category']?.toString() ?? '';
    final gender =
        product['gender']?.toString() ?? '';
    final stock = product['stock']?.toString() ?? '0';
    final badge = product['badge']?.toString();

    final seller = product['seller'];
    String sellerName = '';

    if (seller is Map<String, dynamic>) {
      sellerName = seller['name']?.toString() ?? '';
    }

    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildProductImage(
            imageUrl: imageUrl,
            badge: badge,
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(
              22,
              28,
              22,
              40,
            ),
            child: Column(
              crossAxisAlignment:
                  CrossAxisAlignment.start,
              children: [
                if (sellerName.isNotEmpty) ...[
                  Text(
                    sellerName.toUpperCase(),
                    style: GoogleFonts.inter(
                      fontSize: 9,
                      fontWeight: FontWeight.w600,
                      letterSpacing: 1.5,
                      color: secondaryText,
                    ),
                  ),
                  const SizedBox(height: 8),
                ],
                Text(
                  name,
                  style: GoogleFonts.playfairDisplay(
                    fontSize: 30,
                    fontWeight: FontWeight.w600,
                    color: dark,
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  crossAxisAlignment:
                      CrossAxisAlignment.center,
                  children: [
                    Text(
                      '₱$price',
                      style: GoogleFonts.inter(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                        color: dark,
                      ),
                    ),
                    if (originalPrice != null &&
                        originalPrice.isNotEmpty) ...[
                      const SizedBox(width: 10),
                      Text(
                        '₱$originalPrice',
                        style: GoogleFonts.inter(
                          fontSize: 13,
                          color: secondaryText,
                          decoration:
                              TextDecoration.lineThrough,
                        ),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 24),
                const Divider(
                  color: border,
                ),
                const SizedBox(height: 22),
                if (description.isNotEmpty) ...[
                  Text(
                    'PRODUCT DETAILS',
                    style: GoogleFonts.inter(
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1.4,
                      color: dark,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    description,
                    style: GoogleFonts.inter(
                      fontSize: 13,
                      height: 1.6,
                      color: secondaryText,
                    ),
                  ),
                  const SizedBox(height: 26),
                ],
                _buildDetailRow(
                  'CATEGORY',
                  fashionCategory.isNotEmpty
                      ? fashionCategory
                      : category,
                ),
                if (gender.isNotEmpty)
                  _buildDetailRow(
                    'GENDER',
                    gender,
                  ),
                _buildDetailRow(
                  'STOCK',
                  '$stock available',
                ),
                const SizedBox(height: 28),
                SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton(
                    onPressed: () {},
                    style:
                        ElevatedButton.styleFrom(
                      backgroundColor: dark,
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape:
                          const RoundedRectangleBorder(
                        borderRadius:
                            BorderRadius.zero,
                      ),
                    ),
                    child: Text(
                      'ADD TO BAG',
                      style: GoogleFonts.inter(
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 1.3,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildProductImage({
    required String? imageUrl,
    required String? badge,
  }) {
    return AspectRatio(
      aspectRatio: 1,
      child: Stack(
        children: [
          Container(
            width: double.infinity,
            color: Colors.white,
            child: imageUrl != null &&
                    imageUrl.isNotEmpty
                ? Image.network(
                    imageUrl,
                    fit: BoxFit.cover,
                    errorBuilder: (_, _, _) {
                      return const Center(
                        child: Icon(
                          Icons.image_outlined,
                          color: secondaryText,
                          size: 50,
                        ),
                      );
                    },
                  )
                : const Center(
                    child: Icon(
                      Icons.image_outlined,
                      color: secondaryText,
                      size: 50,
                    ),
                  ),
          ),
          if (badge != null && badge.isNotEmpty)
            Positioned(
              top: 16,
              left: 16,
              child: Container(
                padding:
                    const EdgeInsets.symmetric(
                  horizontal: 9,
                  vertical: 6,
                ),
                color: dark,
                child: Text(
                  badge.toUpperCase(),
                  style: GoogleFonts.inter(
                    fontSize: 8,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 0.7,
                    color: Colors.white,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildDetailRow(
    String label,
    String value,
  ) {
    return Padding(
      padding: const EdgeInsets.only(
        bottom: 14,
      ),
      child: Row(
        crossAxisAlignment:
            CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 90,
            child: Text(
              label,
              style: GoogleFonts.inter(
                fontSize: 9,
                fontWeight: FontWeight.w600,
                letterSpacing: 0.8,
                color: secondaryText,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: GoogleFonts.inter(
                fontSize: 12,
                fontWeight: FontWeight.w500,
                color: dark,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildError({
    String message = 'Unable to load product.',
  }) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment:
              MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.error_outline,
              size: 45,
              color: dark,
            ),
            const SizedBox(height: 15),
            Text(
              message,
              textAlign: TextAlign.center,
              style: GoogleFonts.inter(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: dark,
              ),
            ),
            const SizedBox(height: 8),
            if (_errorMessage != null)
              Text(
                _errorMessage!,
                textAlign: TextAlign.center,
                style: GoogleFonts.inter(
                  fontSize: 11,
                  color: secondaryText,
                ),
              ),
            const SizedBox(height: 20),
            ElevatedButton(
              onPressed: () {
                setState(() {
                  _isLoading = true;
                  _errorMessage = null;
                });

                _loadProduct();
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: dark,
                foregroundColor: Colors.white,
                elevation: 0,
                shape:
                    const RoundedRectangleBorder(
                  borderRadius: BorderRadius.zero,
                ),
              ),
              child: const Text('TRY AGAIN'),
            ),
          ],
        ),
      ),
    );
  }
}