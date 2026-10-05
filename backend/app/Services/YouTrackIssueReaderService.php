<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class YouTrackIssueReaderService
{
    private const FIELDS = 'idReadable,summary,description,project(shortName,name),created,updated,customFields(name,$type,value(name,id,$type))';

    private const TRIAGE_FIELDS = 'idReadable,summary,description,comments(text,author(name),created),'
        . 'attachments(name,url),customFields(name,value(name)),'
        . 'links(direction,linkType(name),issues(idReadable,summary))';

    public function fetchIssue(string $issueId): array
    {
        $baseUrl = config('tickets.youtrack_base_url');
        $token = config('tickets.youtrack_token');

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

    /**
     * Raw issue in the shape /fix-sprint-bugs expects for --ticket-file (its Step 2 fields).
     */
    public function fetchIssueForTriage(string $issueId): array
    {
        $baseUrl = config('tickets.youtrack_base_url');
        $token = config('tickets.youtrack_token');

        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('YOUTRACK_BASE_URL is not set.');
        }
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('YOUTRACK_TOKEN is not set.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->get(rtrim($baseUrl, '/') . '/api/issues/' . $issueId, ['fields' => self::TRIAGE_FIELDS]);

        if ($response->status() === 404) {
            throw new RuntimeException('YouTrack issue not found.', 404);
        }

        if (!$response->successful()) {
            throw new RuntimeException('YouTrack API error: ' . $response->body());
        }

        $payload = $response->json();
        if (!is_array($payload) || !isset($payload['idReadable'])) {
            throw new RuntimeException('YouTrack response was not a valid issue.');
        }

        return $payload;
    }

    /**
     * Readable ids of the issues matching a YouTrack search query, e.g. "tag: ai-fix #Unresolved".
     *
     * @return list<string>
     */
    public function searchIssueIds(string $query, int $limit = 50): array
    {
        $baseUrl = config('tickets.youtrack_base_url');
        $token = config('tickets.youtrack_token');

        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('YOUTRACK_BASE_URL is not set.');
        }
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('YOUTRACK_TOKEN is not set.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->get(rtrim($baseUrl, '/') . '/api/issues', [
                'query' => $query,
                'fields' => 'idReadable',
                '$top' => $limit,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('YouTrack API error: ' . $response->body());
        }

        return collect($response->json())
            ->pluck('idReadable')
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->values()
            ->all();
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
