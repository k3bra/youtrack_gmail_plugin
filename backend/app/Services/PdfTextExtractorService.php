<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Parser;

class PdfTextExtractorService
{
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

        if ($text === '') {
            throw new RuntimeException('PDF text extraction produced empty output.');
        }

        return $text;
    }

    private function isRemovableHeaderFooter(string $line, int $occurrences): bool
    {
        if ($occurrences < 2) {
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
}
