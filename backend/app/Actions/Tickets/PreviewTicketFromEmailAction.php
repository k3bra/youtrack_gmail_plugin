<?php

namespace App\Actions\Tickets;

use App\Services\TicketGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PreviewTicketFromEmailAction
{
    public function handle(Request $request, TicketGeneratorService $ticketGenerator): JsonResponse
    {
        $payload = $request->all();

        $validator = Validator::make($payload, [
            'type' => 'required|in:task,spike',
            'email.subject' => 'required|string',
            'email.body' => 'required|string',
            'email.from' => 'nullable|string',
            'email.threadUrl' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        try {
            $draft = $ticketGenerator->fromEmail([
                'type' => $payload['type'],
                'email' => [
                    'subject' => data_get($payload, 'email.subject'),
                    'from' => data_get($payload, 'email.from'),
                    'body' => data_get($payload, 'email.body'),
                    'threadUrl' => data_get($payload, 'email.threadUrl'),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json([
            'summary' => $draft['summary'],
            'description' => $draft['description'],
            'labels' => $draft['labels'],
        ]);
    }
}
