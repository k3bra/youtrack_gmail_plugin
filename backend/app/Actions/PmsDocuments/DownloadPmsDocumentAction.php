<?php

namespace App\Actions\PmsDocuments;

use App\Models\PmsDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadPmsDocumentAction
{
    public function handle(PmsDocument $pmsDocument): StreamedResponse
    {
        if (!Storage::exists($pmsDocument->storage_path)) {
            abort(404, 'Document not found.');
        }

        return Storage::download($pmsDocument->storage_path, $pmsDocument->original_filename);
    }
}
