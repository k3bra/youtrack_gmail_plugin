<?php

namespace App\Actions\PmsDocuments;

use App\Models\PmsDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Browsershot\Browsershot;

class StorePmsDocumentAction
{
    public function handle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document' => 'required_without:document_url|file|mimes:pdf|max:20480',
            'document_url' => 'required_without:document|url|max:2048',
            'title' => 'nullable|string|max:255',
            'is_booking_engine' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $file = $request->file('document');
        $documentUrl = trim((string) $request->input('document_url', ''));
        $title = trim((string) $request->input('title', ''));
        $title = $title === '' ? null : $title;
        $isBookingEngine = $request->boolean('is_booking_engine');

        try {
            if ($file !== null) {
                $path = $file->store('pms-documents');
                $originalFilename = $file->getClientOriginalName();
                $sourceUrl = null;
            } elseif ($documentUrl !== '') {
                $remote = $this->storeRemoteDocument($documentUrl);
                $path = $remote['path'];
                $originalFilename = $remote['filename'];
                $sourceUrl = $documentUrl;
            } else {
                return response()->json(['error' => 'Document upload or URL is required.'], 400);
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        $document = PmsDocument::create([
            'original_filename' => $originalFilename,
            'storage_path' => $path,
            'source_url' => $sourceUrl,
            'title' => $title,
            'is_booking_engine' => $isBookingEngine,
        ]);

        return response()->json(['id' => $document->id]);
    }

    private function storeRemoteDocument(string $url): array
    {
        $response = null;
        $content = '';
        $contentType = null;
        $contentDisposition = null;

        try {
            $response = Http::timeout(20)->get($url);
        } catch (\Throwable $e) {
            $response = null;
        }

        if ($response && $response->successful()) {
            $content = $response->body();
            $contentType = $response->header('Content-Type');
            $contentDisposition = $response->header('Content-Disposition');
        }

        if ($content !== '' && $this->looksLikePdf($url, $contentType, $content)) {
            $filename = $this->resolveRemoteFilename($url, $contentDisposition, 'pdf');
            return $this->storeRemoteContent($filename, $content);
        }

        $renderedText = $this->renderTextFromUrl($url);
        if ($renderedText !== '' && !$this->isUsefulText($renderedText)) {
            $renderedText = '';
        }
        if ($renderedText !== '') {
            $filename = $this->resolveRemoteFilename($url, null, 'txt');
            return $this->storeRemoteContent($filename, $renderedText);
        }

        $renderedHtml = $this->renderHtmlFromUrl($url);
        if ($renderedHtml !== '') {
            $filename = $this->resolveRemoteFilename($url, null, 'html');
            return $this->storeRemoteContent($filename, $renderedHtml);
        }

        $renderedPdf = $this->renderPdfFromUrl($url);
        if ($renderedPdf !== '' && $this->looksLikePdf($url, 'application/pdf', $renderedPdf)) {
            $filename = $this->resolveRemoteFilename($url, null, 'pdf');
            return $this->storeRemoteContent($filename, $renderedPdf);
        }

        if ($content !== '') {
            $filename = $this->resolveRemoteFilename($url, null, 'html');
            return $this->storeRemoteContent($filename, $content);
        }

        if ($response && !$response->successful()) {
            throw new InvalidArgumentException('Unable to download the document from the provided URL.');
        }

        throw new InvalidArgumentException('Unable to fetch the document from the provided URL.');
    }

    private function looksLikePdf(string $url, ?string $contentType, string $content): bool
    {
        $type = is_string($contentType) ? strtolower($contentType) : '';
        $hasPdfMime = $type !== '' && str_contains($type, 'pdf');

        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) ? strtolower($path) : '';
        $hasPdfExtension = $path !== '' && str_ends_with($path, '.pdf');
        $hasPdfSignature = str_starts_with($content, '%PDF');

        return $hasPdfMime || $hasPdfExtension || $hasPdfSignature;
    }

    private function resolveRemoteFilename(string $url, ?string $contentDisposition, string $extension): string
    {
        $filename = '';

        if (is_string($contentDisposition)) {
            if (preg_match('/filename\\*=UTF-8\\\'\\\'([^;]+)|filename="?(?<name>[^";]+)"?/i', $contentDisposition, $matches)) {
                $filename = $matches['name'] ?? ($matches[1] ?? '');
                $filename = rawurldecode($filename);
            }
        }

        if ($filename === '') {
            $path = parse_url($url, PHP_URL_PATH);
            if (is_string($path)) {
                $filename = basename($path);
            }
        }

        $filename = trim($filename);
        if ($filename === '') {
            $filename = 'pms-document';
        }

        if (!str_ends_with(strtolower($filename), '.' . $extension)) {
            $filename .= '.' . $extension;
        }

        return $filename;
    }

    private function storeRemoteContent(string $filename, string $content): array
    {
        if ($content === '') {
            throw new InvalidArgumentException('Downloaded document was empty.');
        }

        $maxBytes = 20480 * 1024;
        if (strlen($content) > $maxBytes) {
            throw new InvalidArgumentException('Document exceeds the 20MB limit.');
        }

        $path = 'pms-documents/' . Str::uuid() . '-' . $filename;

        if (!Storage::put($path, $content)) {
            throw new InvalidArgumentException('Unable to store the downloaded document.');
        }

        return [
            'path' => $path,
            'filename' => $filename,
        ];
    }

    private function renderHtmlFromUrl(string $url): string
    {
        if (!class_exists(Browsershot::class)) {
            return '';
        }

        try {
            $html = Browsershot::url($url)
                ->timeout(60)
                ->waitUntilNetworkIdle()
                ->delay(3000)
                ->userAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36')
                ->windowSize(1366, 900)
                ->bodyHtml();
        } catch (\Throwable $e) {
            return '';
        }

        return is_string($html) ? trim($html) : '';
    }

    private function renderPdfFromUrl(string $url): string
    {
        if (!class_exists(Browsershot::class)) {
            return '';
        }

        try {
            $pdf = Browsershot::url($url)
                ->timeout(60)
                ->waitUntilNetworkIdle()
                ->delay(3000)
                ->userAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36')
                ->windowSize(1366, 900)
                ->showBackground()
                ->format('A4')
                ->pdf();
        } catch (\Throwable $e) {
            return '';
        }

        return is_string($pdf) ? $pdf : '';
    }

    private function renderTextFromUrl(string $url): string
    {
        if (!class_exists(Browsershot::class)) {
            return '';
        }

        try {
            $text = Browsershot::url($url)
                ->timeout(60)
                ->waitUntilNetworkIdle()
                ->delay(3000)
                ->userAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36')
                ->windowSize(1366, 900)
                ->evaluate('(async () => {'
                    . 'const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));'
                    . 'const cleanup = () => {'
                        . 'document.querySelectorAll("script,style,noscript").forEach(el => el.remove());'
                    . '};'
                    . 'const getInner = () => document.body ? document.body.innerText : "";'
                    . 'const getTextContent = () => document.body ? document.body.textContent : "";'
                    . 'const pickBest = () => {'
                        . 'const inner = getInner();'
                        . 'const raw = getTextContent();'
                        . 'if (raw && raw.length > inner.length * 1.2) { return raw; }'
                        . 'return inner || raw;'
                    . '};'
                    . 'let best = "";'
                    . 'for (let i = 0; i < 5; i++) {'
                        . 'window.scrollTo(0, document.body.scrollHeight);'
                        . 'await sleep(900);'
                        . 'cleanup();'
                        . 'const current = pickBest();'
                        . 'if (current && current.length > best.length) { best = current; }'
                    . '}'
                    . 'return best || pickBest();'
                . '})()');
        } catch (\Throwable $e) {
            return '';
        }

        if (!is_string($text)) {
            return '';
        }

        $cleaned = trim($text);
        if ($cleaned === '') {
            return '';
        }

        return $cleaned;
    }

    private function isUsefulText(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return false;
        }

        $length = strlen($trimmed);
        $wordCount = preg_match_all('/\\b[[:alnum:]]{3,}\\b/u', $trimmed);
        $lineCount = substr_count($trimmed, "\n");

        if ($length < 200 && $wordCount < 30) {
            return false;
        }

        if ($length < 500 && $lineCount < 3) {
            return false;
        }

        return true;
    }
}
