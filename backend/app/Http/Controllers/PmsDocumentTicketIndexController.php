<?php

namespace App\Http\Controllers;

use App\Models\PmsDocumentTicket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PmsDocumentTicketIndexController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tickets = PmsDocumentTicket::query()
            ->with('document')
            ->orderByDesc('created_at')
            ->simplePaginate(20);

        return view('pms-document-tickets', [
            'tickets' => $tickets,
        ]);
    }
}
