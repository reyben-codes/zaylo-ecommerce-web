import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../services/api_service.dart';


class ProductsPage extends StatefulWidget {
  final String token;

  const ProductsPage({
    super.key,
    required this.token,
  });

  @override
  State<ProductsPage> createState() => _ProductsPageState();
}

class _ProductsPageState extends State<ProductsPage> {
  static const background = Color(0xFFFAF7F2);
  static const dark = Color(0xFF1A1714);
  static const secondaryText = Color(0xFF6B5F54);

  bool _isLoading = true;
  String? _errorMessage;
  List<dynamic> _products = [];

  @override
  void initState() {
    super.initState();
    _loadProducts();
  }

  Future<void> _loadProducts() async {
    try {
      final data = await ApiService.getProducts(
        token: widget.token,
        perPage: 20,
      );

      if (!mounted) return;

      setState(() {
        _products = data['data'] as List<dynamic>? ?? [];
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _errorMessage = e.toString().replaceFirst('Exception: ', '');
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
        foregroundColor: dark,
        title: Text(
          'SHOP',
          style: GoogleFonts.playfairDisplay(
            fontSize: 24,
            fontWeight: FontWeight.w600,
            color: dark,
          ),
        ),
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(
                color: dark,
              ),
            )
          : _errorMessage != null
              ? Center(
                  child: Text(
                    _errorMessage!,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: secondaryText,
                    ),
                  ),
                )
              : RefreshIndicator(
                  color: dark,
                  onRefresh: _loadProducts,
                  child: GridView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _products.length,
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      crossAxisSpacing: 12,
                      mainAxisSpacing: 16,
                      childAspectRatio: 0.68,
                    ),
                    itemBuilder: (context, index) {
                      return _buildProductCard(_products[index]);
                    },
                  ),
                ),
    );
  }

  Widget _buildProductCard(dynamic product) {
    final imageUrl = product['image_url'];
    final name = product['name'] ?? 'Product';
    final price = product['price'] ?? '0.00';
    final category = product['category'] ?? '';

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(
          color: const Color(0xFFECE4DB),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: imageUrl != null &&
                    imageUrl.toString().isNotEmpty
                ? Image.network(
                    imageUrl.toString(),
                    width: double.infinity,
                    fit: BoxFit.cover,
                    errorBuilder: (_, _, _) {
                      return const Center(
                        child: Icon(
                          Icons.image_outlined,
                          color: secondaryText,
                          size: 35,
                        ),
                      );
                    },
                  )
                : const Center(
                    child: Icon(
                      Icons.image_outlined,
                      color: secondaryText,
                      size: 35,
                    ),
                  ),
          ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  category.toString().toUpperCase(),
                  style: const TextStyle(
                    fontSize: 8,
                    letterSpacing: 0.8,
                    color: secondaryText,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  name.toString(),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    color: dark,
                  ),
                ),
                const SizedBox(height: 7),
                Text(
                  '₱$price',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: dark,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}