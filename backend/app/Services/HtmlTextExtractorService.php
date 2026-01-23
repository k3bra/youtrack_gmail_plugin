<?php

namespace App\Services;

use RuntimeException;

class HtmlTextExtractorService
{
    public function extractText(string $path): string
    {
        if (!is_file($path)) {
            throw new RuntimeException('Document file does not exist.');
        }

        $html = file_get_contents($path);
        if (!is_string($html) || $html === '') {
            throw new RuntimeException('Document content is empty.');
        }

        $cleaned = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
        $cleaned = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', (string) $cleaned);
        $cleaned = strip_tags((string) $cleaned);
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = preg_split('/\R/u', $cleaned) ?: [];
        $filtered = [];

        foreach ($lines as $line) {
            $trimmed = trim(preg_replace('/\s+/', ' ', $line));
            if ($trimmed === '') {
                continue;
            }
            $filtered[] = $trimmed;
        }

        $text = trim(implode("\n", $filtered));
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        if ($text === '') {
            throw new RuntimeException('Document text extraction produced empty output.');
        }

        return $text;
    }
}
