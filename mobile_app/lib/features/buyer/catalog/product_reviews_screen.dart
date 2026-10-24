import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/components/star_rating.dart';
import 'package:zaylo_mobile/models/buyer/catalog_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

/// Customer reviews of a product: the average, how many gave each rating, and
/// every verified-purchase review, newest first.
class ProductReviewsScreen extends StatefulWidget {
  const ProductReviewsScreen({super.key, required this.product});

  final Product product;

  @override
  State<ProductReviewsScreen> createState() => _ProductReviewsScreenState();
}

class _ProductReviewsScreenState extends State<ProductReviewsScreen> {
  ProductReviewPage? _summary;
  final List<ProductReview> _reviews = [];
  int _page = 0;
  bool _hasMore = true;
  bool _loading = false;
  Object? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadMore());
  }

  Future<void> _loadMore() async {
    if (_loading || !_hasMore) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await AppScope.read(context).api.productReviews(widget.product.slug, page: _page + 1);
      if (!mounted) return;
      setState(() {
        _summary ??= page;
        _reviews.addAll(page.reviews);
        _page = page.currentPage;
        _hasMore = page.hasMore;
      });
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final summary = _summary;
    return Scaffold(
      appBar: AppBar(title: const Text('Customer reviews')),
      body: summary == null
          ? (_error != null ? LoadError(error: _error, onRetry: _loadMore, title: 'Could not load reviews') : const Loader())
          : ListView(padding: const EdgeInsets.all(ZayloSpace.page), children: [
              Text(widget.product.name, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
              const SizedBox(height: 12),
              _Summary(summary),
              const SizedBox(height: 18),
              if (_reviews.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 24),
                  child: Text('No reviews yet. Buyers can rate this product after delivery from My Orders.',
                      textAlign: TextAlign.center),
                ),
              for (final review in _reviews) _ReviewCard(review: review, productName: widget.product.name),
              if (_error != null)
                Center(child: TextButton(onPressed: _loadMore, child: const Text('Could not load more. Retry')))
              else if (_hasMore)
                Center(
                  child: _loading
                      ? const Padding(padding: EdgeInsets.all(12), child: Loader())
                      : TextButton(onPressed: _loadMore, child: const Text('Show more reviews')),
                ),
            ]),
    );
  }
}

class _Summary extends StatelessWidget {
  const _Summary(this.page);

  final ProductReviewPage page;

  @override
  Widget build(BuildContext context) => SectionCard(
        child: Row(children: [
          Column(children: [
            Text(page.average?.toStringAsFixed(1) ?? '–',
                style: TextStyle(fontSize: 36, fontWeight: FontWeight.w800, color: ZayloColors.ink)),
            StarRow(rating: page.average ?? 0),
            const SizedBox(height: 4),
            Text('${page.count} ${page.count == 1 ? 'review' : 'reviews'}', style: const TextStyle(fontSize: 12)),
          ]),
          const SizedBox(width: 18),
          Expanded(
            child: Column(children: [
              for (var stars = 5; stars >= 1; stars--)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 2),
                  child: Row(children: [
                    SizedBox(width: 14, child: Text('$stars', style: const TextStyle(fontSize: 12))),
                    Icon(Icons.star_rounded, size: 13, color: ZayloColors.bronze),
                    const SizedBox(width: 6),
                    Expanded(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(4),
                        child: LinearProgressIndicator(
                          value: page.count == 0 ? 0 : (page.breakdown[stars] ?? 0) / page.count,
                          minHeight: 7,
                          color: ZayloColors.bronze,
                          backgroundColor: ZayloColors.sand,
                          semanticsLabel: '$stars stars',
                          semanticsValue: '${page.breakdown[stars] ?? 0} reviews',
                        ),
                      ),
                    ),
                    SizedBox(
                      width: 28,
                      child: Text('${page.breakdown[stars] ?? 0}', textAlign: TextAlign.end, style: const TextStyle(fontSize: 12)),
                    ),
                  ]),
                ),
            ]),
          ),
        ]),
      );
}

class _ReviewCard extends StatelessWidget {
  const _ReviewCard({required this.review, required this.productName});

  final ProductReview review;
  final String productName;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: SectionCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Expanded(
                child: Text(review.buyerName, style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
              ),
              StarRow(rating: review.rating.toDouble(), size: 15),
            ]),
            const SizedBox(height: 2),
            Text('Verified purchase · ${shortDate(review.createdAt)}',
                style: TextStyle(fontSize: 11, color: ZayloColors.muted)),
            if (review.comment != null && review.comment!.trim().isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(review.comment!, style: TextStyle(color: ZayloColors.ink)),
            ],
            if (review.photos.isNotEmpty) ...[
              const SizedBox(height: 10),
              Wrap(spacing: 8, runSpacing: 8, children: [
                for (final (index, photo) in review.photos.indexed)
                  Semantics(
                    image: true,
                    label: 'Customer photo ${index + 1} for $productName',
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: SizedBox(width: 72, height: 72, child: NetworkImageBox(url: photo)),
                    ),
                  ),
              ]),
            ],
          ]),
        ),
      );
}
