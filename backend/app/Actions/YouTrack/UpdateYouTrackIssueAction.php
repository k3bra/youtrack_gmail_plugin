<?php

namespace App\Actions\YouTrack;

use App\Services\YouTrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UpdateYouTrackIssueAction
{
    public function handle(Request $request, string $issueId, YouTrackService $youTrackService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $description = (string) $request->input('description');

        try {
            $youTrackService->updateIssueDescription($issueId, $description);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json([
            'id' => $issueId,
            'description' => $description,
        ]);
    }
}
