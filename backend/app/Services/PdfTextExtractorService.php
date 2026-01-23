<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Parser;

class PdfTextExtractorService
{
    private const MAX_OUTPUT_CHARS = 80000;

    public function extractText(string $path): string
    {
        if (!is_file($path)) {
            throw new RuntimeException('PDF file does not exist.');
        }

        $parser = new Parser();
        $document = $parser->parseFile($path);
        $pages = $document->getPages();

        if ($pages === []) {
            throw new RuntimeException('PDF parsing returned no pages.');
        }

        $pageLines = [];
        $lineCounts = [];

        foreach ($pages as $pageIndex => $page) {
            $rawText = $page->getText();
            $lines = preg_split('/\R/u', $rawText) ?: [];
            $cleaned = [];

            foreach ($lines as $line) {
                $trimmed = trim(preg_replace('/\s+/', ' ', $line));
                if ($trimmed === '') {
                    continue;
                }
                $cleaned[] = $trimmed;
            }

            $pageLines[$pageIndex] = $cleaned;

            foreach (array_unique($cleaned) as $line) {
                $lineCounts[$line] = ($lineCounts[$line] ?? 0) + 1;
            }
        }

        $rawPages = [];
        foreach ($pageLines as $lines) {
            $rawPages[] = implode("\n", $lines);
        }

        $rawText = trim(implode("\n\n", $rawPages));
        $rawText = preg_replace("/\n{3,}/", "\n\n", $rawText);

        $filteredPages = [];

        foreach ($pageLines as $lines) {
            $kept = [];
            foreach ($lines as $line) {
                if ($this->isRemovableHeaderFooter($line, $lineCounts[$line] ?? 0)) {
                    continue;
                }
                $kept[] = $line;
            }
            $filteredPages[] = implode("\n", $kept);
        }

        $text = trim(implode("\n\n", $filteredPages));
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        if ($text === '' && $rawText !== '') {
            $text = $rawText;
        }

        if ($rawText !== '' && $this->shouldUseRaw($rawText, $text)) {
            $text = $rawText;
        }

        $text = $this->truncateText($text);

        if ($text === '') {
            throw new RuntimeException('PDF text extraction produced empty output.');
        }

        return $text;
    }

    private function isRemovableHeaderFooter(string $line, int $occurrences): bool
    {
        if ($occurrences < 3) {
            return false;
        }

        $length = strlen($line);
        if ($length > 120) {
            return false;
        }

        $likelyEndpoint = preg_match('/\b(GET|POST|PUT|PATCH|DELETE)\b/i', $line) === 1
            || str_contains($line, '/');

        if ($likelyEndpoint) {
            return false;
        }

        return $length <= 80;
    }

    private function shouldUseRaw(string $rawText, string $filteredText): bool
    {
        $rawLength = strlen($rawText);
        $filteredLength = strlen($filteredText);

        if ($rawLength === 0) {
            return false;
        }

        if ($filteredLength === 0) {
            return true;
        }

        if ($rawLength >= 4000 && $filteredLength < (int) ($rawLength * 0.35)) {
            return true;
        }

        if ($filteredLength < 1200 && $rawLength > ($filteredLength + 2000)) {
            return true;
        }

        return false;
    }

    private function truncateText(string $text): string
    {
        if (strlen($text) <= self::MAX_OUTPUT_CHARS) {
            return $text;
        }

        return substr($text, 0, self::MAX_OUTPUT_CHARS) . "\n\n[Truncated]";
    }
}
