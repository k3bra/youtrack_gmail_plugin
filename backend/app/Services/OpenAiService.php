<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class OpenAiService
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const DEFAULT_MODEL = 'gpt-5-mini';
    private const TIMEOUT_SECONDS = 180;

    public function requestJson(string $systemPrompt, string $userPrompt): array
    {
        return $this->fetchJson($systemPrompt, $userPrompt, ['type' => 'json_object']);
    }

    /**
     * Request a response that is guaranteed to match the given JSON schema (OpenAI structured outputs).
     */
    public function requestStructured(
        string $systemPrompt,
        string $userPrompt,
        string $schemaName,
        array $schema
    ): array {
        return $this->fetchJson($systemPrompt, $userPrompt, [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => $schemaName,
                'strict' => true,
                'schema' => $schema,
            ],
        ]);
    }

    private function fetchJson(string $systemPrompt, string $userPrompt, array $responseFormat): array
    {
        $apiKey = config('tickets.openai_key');

        if (!is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not set.');
        }

        $model = config('tickets.openai_model') ?: self::DEFAULT_MODEL;

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->post(self::ENDPOINT, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => $responseFormat,
            ])
            ->throw();

        $body = $response->json();
        if (!is_array($body)) {
            throw new RuntimeException('OpenAI response body was not valid JSON.');
        }

        $message = $body['choices'][0]['message'] ?? [];
        if (is_string($message['refusal'] ?? null) && $message['refusal'] !== '') {
            throw new RuntimeException('OpenAI refused the request: ' . $message['refusal']);
        }

        $content = $message['content'] ?? null;
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
}
