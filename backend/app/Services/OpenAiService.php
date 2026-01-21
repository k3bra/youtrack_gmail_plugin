<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class OpenAiService
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const MODEL = 'gpt-4o-mini';

    public function requestJson(string $systemPrompt, string $userPrompt): array
    {
        return $this->fetchJson($systemPrompt, $userPrompt);
    }

    public function generateTicket(string $systemPrompt, string $userPrompt): array
    {
        $parsed = $this->fetchJson($systemPrompt, $userPrompt);

        $description = $parsed['description'] ?? null;
        if (!is_string($description) || trim($description) === '') {
            $fallback = $this->extractBodyFromPrompt($userPrompt);
            if ($fallback === '') {
                $fallback = 'Description could not be generated automatically.';
            }
            $description = $fallback;
        }

        if (is_string($description)) {
            $parsed['description'] = $this->ensureDescriptionSections(
                $description,
                (string) ($parsed['summary'] ?? ''),
                $userPrompt
            );
        }

        return $parsed;
    }

    private function fetchJson(string $systemPrompt, string $userPrompt): array
    {
        $apiKey = env('OPENAI_API_KEY') ?: config('tickets.openai_key');

        if (!is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not set.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->post(self::ENDPOINT, [
                'model' => self::MODEL,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ])
            ->throw();

        $body = $response->json();
        if (!is_array($body)) {
            throw new RuntimeException('OpenAI response body was not valid JSON.');
        }

        $content = $body['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenAI response content was empty.');
        }

        logger()->debug('OpenAI raw message content', ['content' => $content]);

        $sanitized = $this->sanitizeJsonContent($content);

        try {
            $parsed = json_decode($sanitized, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('OpenAI returned invalid JSON.', 0, $e);
        }

        if (!is_array($parsed)) {
            throw new RuntimeException('OpenAI returned non-object JSON.');
        }

        return $parsed;
    }

    private function sanitizeJsonContent(string $content): string
    {
        $trimmed = trim($content);

        if (preg_match('/^```(?:json)?\s*(.*)\s*```$/si', $trimmed, $matches)) {
            $trimmed = trim($matches[1]);
        }

        if ($trimmed === '') {
            throw new RuntimeException('OpenAI response content was empty after sanitization.');
        }

        $firstChar = $trimmed[0] ?? '';
        $lastChar = $trimmed[strlen($trimmed) - 1] ?? '';
        if ($firstChar !== '{' || $lastChar !== '}') {
            throw new RuntimeException('OpenAI returned non-JSON content.');
        }

        return $trimmed;
    }

    private function extractBodyFromPrompt(string $userPrompt): string
    {
        if (preg_match("/Body:\\s*\\n(.*?)(?:\\n\\nThread URL:|\\z)/s", $userPrompt, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function ensureDescriptionSections(
        string $description,
        string $summary,
        string $userPrompt
    ): string {
        $type = $this->detectType($summary, $userPrompt);
        if ($type === null) {
            return $description;
        }

        $required = $type === 'spike'
            ? ['Context', 'Questions to Answer', 'Unknowns', 'References']
            : ['Context', 'Expected Behavior', 'Acceptance Criteria', 'References'];

        $updated = $description;
        $bodyFallback = $this->extractBodyFromPrompt($userPrompt);
        $threadUrl = $this->extractThreadUrlFromPrompt($userPrompt);
        $contextFallback = $bodyFallback !== '' ? $bodyFallback : 'Context not provided.';

        foreach ($required as $section) {
            if (stripos($updated, $section) !== false) {
                continue;
            }
            $sectionContent = 'Details not provided.';
            if ($section === 'Context') {
                $sectionContent = $contextFallback;
            } elseif ($section === 'References') {
                $sectionContent = $threadUrl !== '' ? $threadUrl : 'None.';
            }
            $updated .= "\n\n{$section}:\n{$sectionContent}";
        }

        return $updated;
    }

    private function detectType(string $summary, string $userPrompt): ?string
    {
        if (stripos($summary, '[Spike]') === 0) {
            return 'spike';
        }
        if (stripos($summary, '[Task]') === 0) {
            return 'task';
        }
        if (stripos($userPrompt, 'Create a SPIKE ticket') !== false) {
            return 'spike';
        }
        if (stripos($userPrompt, 'Create a TASK ticket') !== false) {
            return 'task';
        }

        return null;
    }

    private function extractThreadUrlFromPrompt(string $userPrompt): string
    {
        if (preg_match("/Thread URL:\\s*\\n(.*)$/s", $userPrompt, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }
}
