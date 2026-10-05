<?php

namespace App\Http\Controllers;

use App\Models\TicketRequest;
use App\Models\TicketTriage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Public log of tickets requested from the Gmail plugin, successful and failed.
 */
class PluginTicketIndexController extends Controller
{
    private const STATUSES = ['success', 'failed'];

    public function __invoke(Request $request): View
    {
        $status = in_array($request->query('status'), self::STATUSES, true)
            ? $request->query('status')
            : null;

        $tickets = TicketRequest::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->simplePaginate(25)
            ->withQueryString();

        $issueIds = $tickets->pluck('youtrack_issue_id')->filter()->unique()->values();
        $triages = TicketTriage::query()
            ->with('latestFix')
            ->whereIn('issue_id', $issueIds)
            ->orderByDesc('id')
            ->get()
            ->unique('issue_id')
            ->toBase()
            ->mapWithKeys(fn (TicketTriage $triage) => [$triage->issue_id => $triage->toApiArray()]);

        // Tickets picked up from the ai-fix tag that have no Gmail row on this page.
        $aiFixTriages = TicketTriage::query()
            ->with('latestFix')
            ->where('trigger', TicketTriage::TRIGGER_AI_FIX)
            ->whereNotIn('issue_id', $issueIds)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->unique('issue_id')
            ->take(20)
            ->values();
        $triages = $triages->merge(
            $aiFixTriages->mapWithKeys(fn (TicketTriage $triage) => [$triage->issue_id => $triage->toApiArray()])
        );

        return view('plugin-tickets', [
            'tickets' => $tickets,
            'triages' => $triages,
            'aiFixTriages' => $aiFixTriages,
            'status' => $status,
            'counts' => TicketRequest::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'youTrackBaseUrl' => rtrim((string) config('tickets.youtrack_base_url'), '/'),
        ]);
    }
}
