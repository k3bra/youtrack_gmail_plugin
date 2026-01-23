<?php

namespace App\Http\Controllers;

use App\Actions\PmsDocuments\DownloadPmsDocumentAction;
use App\Models\PmsDocument;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PmsDocumentDownloadController extends Controller
{
    public function show(PmsDocument $pmsDocument, DownloadPmsDocumentAction $action): StreamedResponse
    {
        return $action->handle($pmsDocument);
    }
}
