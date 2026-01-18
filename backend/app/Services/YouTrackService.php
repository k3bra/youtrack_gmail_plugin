<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class YouTrackService
{
    public function createIssue(string $type, string $summary, string $description, array $labels): array
    {
        $baseUrl = env('YOUTRACK_BASE_URL') ?: config('tickets.youtrack_base_url');
        $token = env('YOUTRACK_TOKEN') ?: config('tickets.youtrack_token');
        $projectId = env('YOUTRACK_PROJECT_ID') ?: config('tickets.youtrack_project_id');

        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('YOUTRACK_BASE_URL is not set.');
        }
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('YOUTRACK_TOKEN is not set.');
        }
        if (!is_string($projectId) || $projectId === '') {
            throw new RuntimeException('YOUTRACK_PROJECT_ID is not set.');
        }

        $issueType = strtolower($type) === 'spike' ? 'Spike' : 'Task';

        $customFields = [
            [
                'name' => 'Type',
                'value' => ['name' => $issueType], // Task | Spike
            ],
            [
                'name' => 'State',
                'value' => ['name' => 'Draft'],
            ],
            [
                'name' => 'Team(s)',
                'value' => [
                    ['name' => 'BE'],
                ],
            ],
            [
                'name' => 'Epic Name',
                'value' => ['name' => 'Integrations'],
            ],
            [
                'name' => 'Main topic',
                'value' => ['name' => 'Chatbot'],
            ],
        ];

        $payload = [
            'project' => ['shortName' => $projectId],
            'summary' => $summary,
            'description' => $description,
            'issuetype' => ['name' => $issueType],
            'labels' => array_map(
                static fn (string $label): array => ['name' => $label],
                $labels
            ),
        ];

        if ($customFields) {
            //$payload['customFields'] = $customFields;
        }

        Log::info('Creating YouTrack issue', $payload);
        $endpoint = rtrim($baseUrl, '/') . '/api/issues?fields=idReadable';
        $response = Http::withToken($token)
            ->acceptJson()
            ->post($endpoint, $payload);

        if (!$response->successful()) {
            throw new RuntimeException('YouTrack API error: ' . $response->body());
        }

        $body = $response->json();
        if (!is_array($body)) {
            throw new RuntimeException('YouTrack response was not valid JSON.');
        }

        $issueId = $body['idReadable'] ?? null;
        if (!is_string($issueId) || $issueId === '') {
            throw new RuntimeException('YouTrack response missing idReadable.');
        }

        $url = rtrim($baseUrl, '/') . '/issue/' . $issueId;

        return [
            'issueId' => $issueId,
            'url' => $url,
        ];
    }
}
