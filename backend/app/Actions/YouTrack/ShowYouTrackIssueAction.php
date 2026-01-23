<?php

namespace App\Actions\YouTrack;

use App\Services\YouTrackIssueReaderService;
use Illuminate\Http\JsonResponse;

class ShowYouTrackIssueAction
{
    public function handle(string $issueId, YouTrackIssueReaderService $reader): JsonResponse
    {
        try {
            $issue = $reader->fetchIssue($issueId);
        } catch (\Throwable $e) {
            $code = $e->getCode();
            $status = is_int($code) && $code >= 400 && $code < 600 ? $code : 502;
            return response()->json(['error' => $e->getMessage()], $status);
        }

        return response()->json($issue);
    }
}
