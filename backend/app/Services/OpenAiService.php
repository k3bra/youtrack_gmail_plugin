<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class OpenAiService
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const MODEL = 'gpt-4o-mini';

    public function generateTicket(string $systemPrompt, string $userPrompt): array
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

        try {
            $parsed = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('OpenAI returned invalid JSON.', 0, $e);
        }

        if (!is_array($parsed)) {
            throw new RuntimeException('OpenAI returned non-object JSON.');
        }

        return $parsed;
    }
}
