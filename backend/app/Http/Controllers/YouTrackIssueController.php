<?php

namespace App\Http\Controllers;

use App\Actions\YouTrack\ShowYouTrackIssueAction;
use App\Actions\YouTrack\UpdateYouTrackIssueAction;
use App\Services\YouTrackIssueReaderService;
use App\Services\YouTrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YouTrackIssueController extends Controller
{
    public function show(
        string $issueId,
        ShowYouTrackIssueAction $action,
        YouTrackIssueReaderService $reader
    ): JsonResponse {
        return $action->handle($issueId, $reader);
    }

    public function update(
        Request $request,
        string $issueId,
        UpdateYouTrackIssueAction $action,
        YouTrackService $youTrackService
    ): JsonResponse {
        return $action->handle($request, $issueId, $youTrackService);
    }
}
