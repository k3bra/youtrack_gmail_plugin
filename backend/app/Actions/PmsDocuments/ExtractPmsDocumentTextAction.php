<?php

namespace App\Actions\PmsDocuments;

use App\Models\PmsDocument;
use App\Services\HtmlTextExtractorService;
use App\Services\PdfTextExtractorService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExtractPmsDocumentTextAction
{
    public function __construct(
        private PdfTextExtractorService $pdfTextExtractor,
        private HtmlTextExtractorService $htmlTextExtractor
    ) {
    }

    public function handle(PmsDocument $pmsDocument): string
    {
        $path = Storage::path($pmsDocument->storage_path);
        if (!is_string($path) || $path === '') {
            throw new RuntimeException('Document storage path is invalid.');
        }

        if ($this->isPdfPath($path)) {
            return $this->pdfTextExtractor->extractText($path);
        }

        return $this->htmlTextExtractor->extractText($path);
    }

    private function isPdfPath(string $path): bool
    {
        return str_ends_with(strtolower($path), '.pdf');
    }
}
