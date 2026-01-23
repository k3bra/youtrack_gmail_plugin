<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class YouTrackIssueReaderService
{
    private const FIELDS = 'idReadable,summary,description,project(shortName,name),created,updated,customFields(name,$type,value(name,id,$type))';

    public function fetchIssue(string $issueId): array
    {
        $baseUrl = env('YOUTRACK_BASE_URL') ?: config('tickets.youtrack_base_url');
        $token = env('YOUTRACK_TOKEN') ?: config('tickets.youtrack_token');

        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('YOUTRACK_BASE_URL is not set.');
        }
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('YOUTRACK_TOKEN is not set.');
        }

        $endpoint = rtrim($baseUrl, '/') . '/api/issues/' . $issueId;
        $response = Http::withToken($token)
            ->acceptJson()
            ->get($endpoint, ['fields' => self::FIELDS]);


        Log::info('YouTrack issue fetch', ['response' => $response->json()]);

        if ($response->status() === 404) {
            throw new RuntimeException('YouTrack issue not found.', 404);
        }

        if (!$response->successful()) {
            throw new RuntimeException('YouTrack API error: ' . $response->body());
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            throw new RuntimeException('YouTrack response was not valid JSON.');
        }

        $project = $payload['project'] ?? [];
        $customFields = $payload['customFields'] ?? [];

        return [
            'id' => $payload['idReadable'] ?? null,
            'summary' => $payload['summary'] ?? null,
            'description' => $payload['description'] ?? null,
            'project' => [
                'key' => $project['shortName'] ?? null,
                'name' => $project['name'] ?? null,
            ],
            'fields' => $this->normalizeCustomFields($customFields),
        ];
    }

    private function normalizeCustomFields(mixed $customFields): array
    {
        if (!is_array($customFields)) {
            return [];
        }

        $normalized = [];

        foreach ($customFields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = $field['name'] ?? null;
            if (!is_string($name) || $name === '') {
                continue;
            }

            $normalized[$name] = $this->normalizeFieldValue($field['value'] ?? null);
        }

        return $normalized;
    }

    private function normalizeFieldValue(mixed $value): string|array|null
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $names = [];
                foreach ($value as $entry) {
                    if (is_array($entry) && isset($entry['name']) && is_string($entry['name'])) {
                        $names[] = $entry['name'];
                    } elseif (is_string($entry)) {
                        $names[] = $entry;
                    }
                }

                return $names !== [] ? $names : null;
            }

            if (isset($value['name']) && is_string($value['name'])) {
                return $value['name'];
            }

            return null;
        }

        if (is_string($value) || is_numeric($value) || is_bool($value)) {
            return (string) $value;
        }

        return null;
    }
}
