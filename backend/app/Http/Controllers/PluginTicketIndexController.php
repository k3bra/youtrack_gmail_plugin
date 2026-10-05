<?php

namespace App\Http\Controllers;

use App\Models\TicketRequest;
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

        return view('plugin-tickets', [
            'tickets' => $tickets,
            'status' => $status,
            'counts' => TicketRequest::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'youTrackBaseUrl' => rtrim((string) config('tickets.youtrack_base_url'), '/'),
        ]);
    }
}
