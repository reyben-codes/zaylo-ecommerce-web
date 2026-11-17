<?php

$operations = ['logistics.pickups', 'logistics.incoming', 'logistics.sorting', 'logistics.scan',
    'logistics.assignments', 'logistics.deliveries', 'logistics.returns', 'logistics.monitoring',
    'logistics.parcels.waybill', 'logistics.parcels.waybill.print', 'logistics.parcels.pickup',
    'logistics.parcels.courier', 'logistics.parcels.pickup-rider', 'logistics.parcels.sort',
    'logistics.parcels.advance', 'logistics.returns.scan', 'logistics.returns.assign',
    'logistics.parcels.assign', 'logistics.areas.assign'];
$chat = ['logistics.messages', 'logistics.messages.start', 'logistics-chat.messages', 'logistics-chat.send', 'logistics-chat.media'];

return [
    'common' => ['logistics.dashboard', 'logistics.account', 'logistics.account.profile', 'logistics.account.password',
        'logistics.updates', 'logistics.notifications', 'logistics.notifications.open', 'logistics.notifications.read-all',
        'support', 'support.send', 'support.messages', 'support.media', 'faq', 'faq.ask', 'logout',
        'account-reports.index', 'account-reports.store',
        'verification.notice', 'verification.verify', 'verification.send', 'login', 'logistics.login',
        'terms', 'policies.show', 'storage.public'],
    'positions' => [
        'hub_supervisor' => [
            'label' => 'Hub Supervisor',
            'description' => 'Manage parcel operations, rider applications, schedules, attendance and operational reports.',
            'routes' => array_merge($operations, $chat, ['logistics.couriers', 'logistics.riders', 'logistics.riders.document',
                'logistics.riders.update', 'logistics.riders.availability', 'logistics.riders.type', 'logistics.riders.schedule',
                'logistics.attendance', 'logistics.attendance.code', 'logistics.reports', 'logistics.reports.export']),
            'advance' => ['picked_up', 'in_transit', 'returned'],
        ],
        'dispatcher' => [
            'label' => 'Dispatcher',
            'description' => 'Confirm pickups, assign pickup and delivery riders, monitor deliveries and coordinate messages.',
            'routes' => array_merge($chat, ['logistics.pickups', 'logistics.assignments', 'logistics.deliveries', 'logistics.monitoring',
                'logistics.parcels.waybill', 'logistics.parcels.waybill.print', 'logistics.parcels.pickup', 'logistics.parcels.courier',
                'logistics.parcels.pickup-rider', 'logistics.parcels.assign', 'logistics.areas.assign', 'logistics.parcels.advance',
                'logistics.returns', 'logistics.returns.assign']),
            'advance' => ['picked_up'],
        ],
        'sorting_staff' => [
            'label' => 'Sorting Staff',
            'description' => 'Scan parcels, receive hub handovers, sort parcels and record return receipts.',
            'routes' => ['logistics.incoming', 'logistics.sorting', 'logistics.scan', 'logistics.returns', 'logistics.parcels.sort',
                'logistics.parcels.advance', 'logistics.returns.scan', 'logistics.parcels.waybill', 'logistics.parcels.waybill.print'],
            'advance' => ['in_transit', 'returned'],
        ],
        'finance_staff' => [
            'label' => 'Finance Staff',
            'description' => 'Record COD handovers and remittances, seller COD transfers and rider salary releases. No company withdrawals.',
            'routes' => ['logistics.cash', 'logistics.cash.record', 'logistics.cash.settlements.settle',
                'logistics.cash.settlements.admin-transfer', 'logistics.rider-withdrawals', 'logistics.rider-withdrawals.pay',
                'logistics.rider-withdrawals.reject', 'cod-history.show'],
            'advance' => [],
        ],
    ],
];
