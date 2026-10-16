<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin Center: platform commission (10% of each completed sale) and the sales and commission reports.
 */
class AdminFinanceController extends Controller
{
    public const COMMISSION_STATES = [
        'earned' => 'Earned',
        'unrecorded' => 'Not recorded yet',
        'settled' => 'Settled',
        'pending' => 'Pending (order not finished)',
        'none' => 'None (cancelled)',
    ];

    public function __construct(private MarketplaceLedger $ledger) {}

    public function commissions(Request $request)
    {
        [$from, $to, $range] = $this->range($request, defaultDays: 90);
        $state = $request->validate(['state' => ['nullable', Rule::in(array_keys(self::COMMISSION_STATES))]])['state'] ?? null;
        $rows = $this->ledger->rows($from, $to);

        return view('admin.commissions', [
            'range' => $range,
            'rate' => MarketplaceLedger::rate(),
            'summary' => $this->ledger->summary($rows),
            'bySeller' => $this->ledger->bySeller($rows),
            'rows' => $rows->when($state, fn ($rows) => $rows->where('commission_status', $state))->sortByDesc('placed')->values(),
            'state' => $state,
            'states' => self::COMMISSION_STATES,
        ]);
    }

    /** Calculates and records the commission of completed orders that do not have one yet. */
    public function recordCommissions()
    {
        $added = $this->ledger->recordMissingCommissions();

        return back()->with('status', $added ? "Recorded the {$this->percent()} commission of {$added} completed ".str('order')->plural($added).'.' : 'Every completed order already has its commission recorded.');
    }

    /** Marks earned commissions as collected from the seller. */
    public function settleCommissions(Request $request)
    {
        $ids = $request->validate(['commissions' => ['required', 'array', 'min:1'], 'commissions.*' => ['integer']], ['commissions.required' => 'Select at least one earned commission to settle.'])['commissions'];
        $settled = $this->ledger->settle($ids);

        return back()->with('status', $settled ? "{$settled} ".str('commission')->plural($settled).' marked as settled.' : 'Nothing was settled. Only earned commissions can be settled.');
    }

    public function reports(Request $request)
    {
        [$from, $to, $range] = $this->range($request, defaultDays: 30);

        return view('admin.reports', $this->report($from, $to) + ['range' => $range]);
    }

    /** The sales summary or commission report as a PDF. */
    public function reportPdf(Request $request, string $report)
    {
        abort_unless(in_array($report, ['sales', 'commission'], true), 404);
        [$from, $to, $range] = $this->range($request, defaultDays: 30);
        $data = $this->report($from, $to) + [
            'range' => $range,
            'report' => $report,
            'generatedAt' => now(MarketplaceLedger::TIMEZONE),
            'generatedBy' => $request->user()->name,
        ];

        $pdf = Pdf::loadView('admin.report-pdf', $data)->setPaper('a4', 'portrait')->setOption('isFontSubsettingEnabled', true);
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text($canvas->get_width() - 90, $canvas->get_height() - 24, 'Page {PAGE_NUM} of {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.42, 0.37, 0.33]);

        return $pdf->download("zaylo-{$report}-report-{$range['from']}-to-{$range['to']}.pdf");
    }

    private function report(Carbon $from, Carbon $to): array
    {
        $rows = $this->ledger->rows($from, $to);

        return [
            'rate' => MarketplaceLedger::rate(),
            'summary' => $this->ledger->summary($rows),
            'bySeller' => $this->ledger->bySeller($rows),
            'series' => $this->ledger->series($rows, $from, $to),
            'monthly' => $from->diffInDays($to) > 62,
            'topProducts' => $this->ledger->topProducts($rows),
            'byStatus' => $rows->groupBy('status')->map(fn ($group) => $group->pluck('order_id')->unique()->count())->sortDesc(),
            'rows' => $rows,
        ];
    }

    /** @return array{0: Carbon, 1: Carbon, 2: array{from: string, to: string, label: string}} */
    private function range(Request $request, int $defaultDays): array
    {
        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $zone = MarketplaceLedger::TIMEZONE;
        $to = isset($input['to']) ? Carbon::createFromFormat('Y-m-d', $input['to'], $zone)->endOfDay() : now($zone)->endOfDay();
        $from = isset($input['from']) ? Carbon::createFromFormat('Y-m-d', $input['from'], $zone)->startOfDay() : $to->copy()->subDays($defaultDays - 1)->startOfDay();
        if ($from->gt($to)) {
            throw ValidationException::withMessages(['from' => 'The start date must be on or before the end date.']);
        }
        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['from' => 'Choose a range of one year or less.']);
        }

        return [$from->copy()->utc(), $to->copy()->utc(), [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'label' => $from->format('M j, Y').' – '.$to->format('M j, Y'),
        ]];
    }

    private function percent(): string
    {
        return rtrim(rtrim(number_format(MarketplaceLedger::rate() * 100, 2), '0'), '.').'%';
    }
}
