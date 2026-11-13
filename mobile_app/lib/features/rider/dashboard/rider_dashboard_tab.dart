import 'dart:async';

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/announcement_banner.dart';
import 'package:zaylo_mobile/components/announcement_popups.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/map/zaylo_map.dart' show LocationAccess;
import 'package:zaylo_mobile/features/rider/dashboard/rider_delivery_list_screen.dart';
import 'package:zaylo_mobile/features/rider/dashboard/rider_earnings_screen.dart';
import 'package:zaylo_mobile/features/rider/dashboard/rider_location_access.dart';
import 'package:zaylo_mobile/features/rider/dashboard/rider_location_reporter.dart';
import 'package:zaylo_mobile/features/rider/dashboard/rider_reports_screen.dart';
import 'package:zaylo_mobile/features/rider/dashboard/rider_scan_screen.dart';
import 'package:zaylo_mobile/features/rider/dashboard/widgets/rider_dashboard_widgets.dart';
import 'package:zaylo_mobile/features/rider/dashboard/widgets/rider_delivery_actions.dart';
import 'package:zaylo_mobile/models/rider/rider_activity_models.dart';
import 'package:zaylo_mobile/models/rider/rider_dashboard_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// The Home tab: today's attendance (time in / time out by scanning the hub's
/// QR code), delivery counts and earnings for a period the rider picks (each
/// opens its own list), new assignments to accept or decline, returned items
/// to collect from buyers, deliveries in progress with their next step, and
/// the most recent completed ones.
class RiderDashboardTab extends StatefulWidget {
  const RiderDashboardTab({super.key, required this.api, required this.onOpenHistory});

  final RiderApi api;

  /// Switches to the History tab filtered to a period.
  final ValueChanged<RiderPeriod> onOpenHistory;

  @override
  State<RiderDashboardTab> createState() => RiderDashboardTabState();
}

class RiderDashboardTabState extends State<RiderDashboardTab> with RiderDeliveryActions {
  late final _reporter = RiderLocationReporter(api: widget.api);
  late Future<RiderDashboard> _dashboard = _loadDashboard();
  bool _attendanceBusy = false;

  /// The period the earnings card shows; kept while the app is open.
  RiderPeriod _earningsPeriod = RiderPeriod.today;

  @override
  RiderApi get riderApi => widget.api;

  /// Where the phone's location stands; Home shows a banner until riders can be seen on the map.
  LocationAccess? _location;
  late final _lifecycle = AppLifecycleListener(onResume: _checkLocation);

  /// Asked once per app session, so riders are not nagged every time Home opens.
  static bool _askedThisSession = false;

  @override
  void initState() {
    super.initState();
    _lifecycle; // starts listening, to pick up changes made in the phone's settings
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await _checkLocation();
      if (!mounted || _askedThisSession || _location == null || _location == LocationAccess.granted) return;
      _askedThisSession = true;
      await _fixLocation();
    });
  }

  Future<void> _checkLocation() async {
    final status = await riderLocationStatus();
    if (mounted) setState(() => _location = status);
  }

  /// Explains why, then shows the system prompt or opens the right settings page.
  Future<void> _fixLocation() async {
    final status = await askRiderLocation(context);
    if (!mounted) return;
    setState(() => _location = status);
    // Now allowed: start sharing straight away if the rider is on shift or delivering.
    if (status == LocationAccess.granted) unawaited(reloadDeliveries());
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    _reporter.dispose();
    super.dispose();
  }

  /// Loads the dashboard and shares the rider's location while a parcel is out for delivery.
  Future<RiderDashboard> _loadDashboard() async {
    final dashboard = await widget.api.dashboard();
    _reporter.follow(dashboard);
    return dashboard;
  }

  @override
  Future<void> reloadDeliveries() async {
    final dashboard = _loadDashboard();
    setState(() {
      _dashboard = dashboard;
    });
    try {
      await dashboard;
    } on Exception {
      // Shown by the FutureBuilder's error state.
    }
  }

  /// Opens a screen, then refreshes: the rider may have accepted or delivered there.
  Future<void> _open(Widget screen) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));
    if (mounted) await reloadDeliveries();
  }

  /// Scans the attendance QR code at the hub: the first scan of the day times in, the next one times out.
  Future<void> _scanAttendance(RiderAttendance attendance) async {
    final code = await Navigator.of(context).push<String>(MaterialPageRoute(
      builder: (_) => RiderScanScreen(
        title: attendance.canTimeOut ? 'Time out' : 'Time in',
        hint: "Scan the attendance QR code on your logistics hub's screen.",
        instructions: 'The code changes every 30 seconds, so scan it at the hub, not from a photo.',
        manualEntry: false,
        accept: (code) => RiderAttendance.isAttendanceCode(code)
            ? null
            : "That is not the attendance code. Scan the QR code on the hub's attendance screen.",
      ),
    ));
    if (code == null || !mounted) return;
    setState(() => _attendanceBusy = true);
    try {
      final result = await widget.api.scanAttendance(code);
      if (mounted) showMessage(context, result.message);
      await reloadDeliveries();
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Attendance scan failed: ${error.debugDescription}');
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _attendanceBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<RiderDashboard>(
        future: _dashboard,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done && !snapshot.hasData) return const Loader();
          if (snapshot.hasError) {
            return LoadError(error: snapshot.error, onRetry: reloadDeliveries, title: 'Could not load your dashboard');
          }
          riderReasons = snapshot.requireData.reasons;
          return RefreshIndicator(
            color: ZayloColors.ink,
            onRefresh: reloadDeliveries,
            child: _content(snapshot.requireData),
          );
        },
      );

  Widget _content(RiderDashboard data) =>
      data.rider.isPickupRider ? _pickupRiderContent(data) : _deliveryRiderContent(data);

  Widget _pickupRiderContent(RiderDashboard data) {
    final assignedQueue = data.sellerPickups.where((p) => p.status == 'ready' || p.status == 'confirmed').toList();
    final inTransit = data.sellerPickups.where((p) => p.status == 'picked_up' && p.receivedAt == null).toList();
    final atHub = data.sellerPickups.where((p) => p.receivedAt != null || p.status == 'handed_over').toList();

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.fromLTRB(20, 8, 20, 32 + MediaQuery.paddingOf(context).bottom),
      children: [
        RiderGreetingCard(rider: data.rider),
        if (_location != null && _location != LocationAccess.granted) ...[
          const SizedBox(height: 12),
          RiderLocationBanner(status: _location!, onFix: _fixLocation),
        ],
        AnnouncementPopups(announcements: data.announcementPopups),
        if (data.announcements.isNotEmpty) ...[
          const SizedBox(height: 12),
          AnnouncementBanner(announcements: data.announcements),
        ],
        if (data.attendance != null) ...[
          const SizedBox(height: 16),
          RiderAttendanceCard(
            attendance: data.attendance!,
            busy: _attendanceBusy,
            onScan: busyDeliveryId == null ? () => _scanAttendance(data.attendance!) : null,
          ),
        ],
        const SizedBox(height: 16),
        SizedBox(
          width: double.infinity,
          child: FilledButton.icon(
            onPressed: busyDeliveryId == null ? scanSellerPickup : null,
            style: FilledButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 14),
              backgroundColor: ZayloColors.ink,
            ),
            icon: const Icon(Icons.qr_code_scanner),
            label: const Text('SCAN SELLER PICKUP', style: TextStyle(fontWeight: FontWeight.w700)),
          ),
        ),
        const SizedBox(height: 6),
        Text(
          'Scan waybill barcode at the seller location. This moves the parcel into your active transit manifest.',
          style: TextStyle(fontSize: 12, color: ZayloColors.muted),
        ),
        const SizedBox(height: 16),
        RiderSectionHeader(title: 'Assigned Pickups Queue', count: assignedQueue.length),
        if (assignedQueue.isEmpty)
          const RiderEmptyCard(
            icon: Icons.storefront_outlined,
            message: 'No seller pickups currently assigned. Logistics partner assigns pickups based on route.',
          )
        else
          for (final pickup in assignedQueue)
            Card(
              margin: const EdgeInsets.only(bottom: 12),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(pickup.trackingNumber, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: ZayloColors.ink.withValues(alpha: 0.08),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(pickup.statusLabel, style: TextStyle(color: ZayloColors.ink, fontSize: 11, fontWeight: FontWeight.w600)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text('Seller: ${pickup.seller}', style: const TextStyle(fontWeight: FontWeight.w600)),
                  Text(pickup.address, style: TextStyle(color: ZayloColors.muted, fontSize: 13)),
                  const SizedBox(height: 8),
                  for (final item in pickup.items)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 1),
                      child: Text('• $item', style: const TextStyle(fontSize: 13)),
                    ),
                  if (pickup.canScan) ...[
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        onPressed: busyDeliveryId == null ? () => scanSellerPickup(pickup) : null,
                        icon: const Icon(Icons.qr_code_scanner),
                        label: const Text('Scan & Handover'),
                      ),
                    ),
                  ],
                ]),
              ),
            ),
        const SizedBox(height: 16),
        RiderSectionHeader(title: 'Active Transit Manifest', count: inTransit.length),
        if (inTransit.isEmpty)
          const RiderEmptyCard(
            icon: Icons.local_shipping_outlined,
            message: 'No parcels currently in transit. Scanned pickups will remain here until received at hub.',
          )
        else
          for (final pickup in inTransit)
            Card(
              margin: const EdgeInsets.only(bottom: 12),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(pickup.trackingNumber, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: ZayloColors.warning.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text('In Transit to Hub', style: TextStyle(color: ZayloColors.warning, fontSize: 11, fontWeight: FontWeight.w600)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text('Seller: ${pickup.seller}', style: const TextStyle(fontWeight: FontWeight.w500)),
                  if (pickup.scannedAt != null)
                    Text('Scanned: ${pickup.scannedAt}', style: TextStyle(color: ZayloColors.muted, fontSize: 12)),
                ]),
              ),
            ),
        const SizedBox(height: 16),
        RiderSectionHeader(title: 'Hub Drop-off & Receiving', count: atHub.length),
        if (atHub.isEmpty)
          const RiderEmptyCard(
            icon: Icons.warehouse_outlined,
            message: 'No completed hub drop-offs yet.',
          )
        else
          for (final pickup in atHub)
            Card(
              margin: const EdgeInsets.only(bottom: 12),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(pickup.trackingNumber, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: ZayloColors.success.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text('Received at Hub', style: TextStyle(color: ZayloColors.success, fontSize: 11, fontWeight: FontWeight.w600)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  if (pickup.receivedAt != null)
                    Text('Received: ${pickup.receivedAt}', style: TextStyle(color: ZayloColors.muted, fontSize: 12)),
                ]),
              ),
            ),
      ],
    );
  }

  Widget _deliveryRiderContent(RiderDashboard data) => ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(20, 8, 20, 32 + MediaQuery.paddingOf(context).bottom),
        children: [
          RiderGreetingCard(rider: data.rider),
          if (_location != null && _location != LocationAccess.granted) ...[
            const SizedBox(height: 12),
            RiderLocationBanner(status: _location!, onFix: _fixLocation),
          ],
          AnnouncementPopups(announcements: data.announcementPopups),
          if (data.announcements.isNotEmpty) ...[
            const SizedBox(height: 12),
            AnnouncementBanner(announcements: data.announcements),
          ],
          if (data.deliveryModeMessage != null) ...[
            const SizedBox(height: 12),
            RiderEmptyCard(icon: Icons.storefront_outlined, message: data.deliveryModeMessage!),
          ],
          if (data.rider.licenseExpiringSoon) ...[
            const SizedBox(height: 12),
            LicenseWarningBanner(rider: data.rider),
          ],
          if (data.attendance != null) ...[
            const SizedBox(height: 16),
            RiderAttendanceCard(
              attendance: data.attendance!,
              busy: _attendanceBusy,
              onScan: busyDeliveryId == null ? () => _scanAttendance(data.attendance!) : null,
            ),
          ],
          const SizedBox(height: 16),
          RiderStatsGrid(
            stats: data.stats,
            onActive: () => _open(RiderDeliveryListScreen(api: widget.api)),
            onDelivered: () => widget.onOpenHistory(RiderPeriod.all),
          ),
          const SizedBox(height: 12),
          RiderEarningsCard(
            stats: data.stats,
            period: _earningsPeriod,
            onPeriodChanged: (period) => setState(() => _earningsPeriod = period),
            onOpenHistory: widget.onOpenHistory,
            onOpenEarnings: () => _open(RiderEarningsScreen(
              api: widget.api,
              onOpenHistory: (period) {
                Navigator.of(context).pop();
                widget.onOpenHistory(period);
              },
            )),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () => _open(RiderReportsScreen(api: widget.api)),
              style: OutlinedButton.styleFrom(
                foregroundColor: ZayloColors.ink,
                side: BorderSide(color: ZayloColors.line),
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
              icon: const Icon(Icons.insights_outlined),
              label: const Text('VIEW REPORTS', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
          ),

          // Always shown, so riders know where scanning lives; off while sellers deliver their own orders.
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed:
                  busyDeliveryId == null && data.deliveryModeMessage == null ? () => scanAnyParcel(data.active) : null,
              style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('SCAN PARCEL', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
          ),
          const SizedBox(height: 6),
          Text(
              data.deliveryModeMessage != null
                  ? 'Scanning turns on when your logistics partner starts assigning parcels to riders.'
                  : 'Scan a label to start a delivery, and again at the door to confirm it. '
                      'The buyer and seller are notified automatically.',
              style: TextStyle(fontSize: 12, color: ZayloColors.muted)),

          if (data.newAssignments.isNotEmpty) ...[
            const SizedBox(height: 16),
            RiderSectionHeader(title: 'New assignments', count: data.newAssignments.length),
            for (final delivery in data.newAssignments) _card(delivery),
          ],

          if (data.returns.isNotEmpty) ...[
            const SizedBox(height: 16),
            RiderSectionHeader(title: 'Returns to collect', count: data.returns.length),
            for (final item in data.returns)
              RiderReturnCard(
                item: item,
                busy: busyDeliveryId == -item.id,
                onScan: item.canScan && busyDeliveryId == null ? () => collectReturn(item) : null,
              ),
          ],

          const SizedBox(height: 16),
          RiderSectionHeader(title: 'Your active deliveries', count: data.inProgress.length),
          if (data.inProgress.isEmpty)
            RiderEmptyCard(
              icon: Icons.local_shipping_outlined,
              message: data.newAssignments.isEmpty
                  ? 'No deliveries right now. Your logistics partner assigns parcels to you when they are ready.'
                  : 'Accept a new assignment above to start delivering it.',
            )
          else
            for (final delivery in data.inProgress) _card(delivery),

          if (data.recent.isNotEmpty) ...[
            const SizedBox(height: 16),
            const RiderSectionHeader(title: 'Recently delivered'),
            for (final delivery in data.recent)
              RiderDeliveryCard(delivery: delivery, onTap: () => openDeliveryDetails(delivery)),
            TextButton(onPressed: () => widget.onOpenHistory(RiderPeriod.all), child: const Text('See all delivery history')),
          ],
        ],
      );

  Widget _card(RiderDelivery delivery) => RiderDeliveryCard(
        delivery: delivery,
        actionLabel: delivery.nextActionLabel,
        busy: busyDeliveryId == delivery.id,
        onAction: busyDeliveryId == null ? () => advanceDelivery(delivery) : null,
        onOtherAction: busyDeliveryId == null ? (status) => advanceDelivery(delivery, status) : null,
        onAccept: busyDeliveryId == null ? () => acceptAssignment(delivery) : null,
        onDecline: busyDeliveryId == null ? () => declineAssignment(delivery) : null,
        onCollectCod: busyDeliveryId == null ? () => collectCod(delivery) : null,
        onChat: () => openDeliveryChat(delivery),
        onTap: () => openDeliveryDetails(delivery),
      );
}
