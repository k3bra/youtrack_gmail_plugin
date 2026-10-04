<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class YouTrackService
{
    private const PRIORITY_FIELD = 'Priority';
    private const PRIORITY_CACHE_SECONDS = 3600;
    private const PROPOSAL_TAG_PATTERN = '/^proposal(\d+)$/';
    private const PROPOSAL_CACHE_SECONDS = 600;
    private const ADDED_SPRINT_TAG_PREFIX = 'added-sprint';
    private const DEFAULT_AGILE_NAME = 'Product Sprint';
    private const SPRINT_CACHE_SECONDS = 600;

    /**
     * Current sprint of the configured agile board, e.g. ['agileId' => '193-5', 'id' => '208-559', 'name' => 'Sprint 150', 'number' => 150].
     */
    public function fetchCurrentSprint(): ?array
    {
        [$baseUrl, $token] = $this->credentials();
        $agileName = config('tickets.youtrack_agile_name') ?: self::DEFAULT_AGILE_NAME;

        return Cache::remember(
            'youtrack.current_sprint.' . md5($agileName),
            self::SPRINT_CACHE_SECONDS,
            function () use ($baseUrl, $token, $agileName): ?array {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->get(rtrim($baseUrl, '/') . '/api/agiles', [
                        'fields' => 'id,name,currentSprint(id,name)',
                        '$top' => 200,
                    ]);

                if (!$response->successful()) {
                    throw new RuntimeException('YouTrack API error: ' . $response->body());
                }

                foreach ($response->json() ?? [] as $agile) {
                    if (($agile['name'] ?? null) !== $agileName) {
                        continue;
                    }

                    $sprint = $agile['currentSprint'] ?? null;
                    if (!is_array($sprint) || !is_string($sprint['name'] ?? null)
                        || !preg_match('/(\d+)/', $sprint['name'], $matches)) {
                        return null;
                    }

                    return [
                        'agileId' => $agile['id'],
                        'id' => $sprint['id'],
                        'name' => $sprint['name'],
                        'number' => (int) $matches[1],
                    ];
                }

                throw new RuntimeException("YouTrack agile board \"{$agileName}\" was not found.");
            }
        );
    }

    /**
     * Returns the added-sprint{N} tag, creating it with the same visibility as the previous one if needed.
     */
    public function findOrCreateAddedSprintTag(int $sprintNumber): array
    {
        [$baseUrl, $token] = $this->credentials();
        $name = self::ADDED_SPRINT_TAG_PREFIX . $sprintNumber;

        $response = Http::withToken($token)
            ->acceptJson()
            ->get(rtrim($baseUrl, '/') . '/api/issueTags', [
                'fields' => 'id,name,visibleFor(id),usableFor(id)',
                'query' => self::ADDED_SPRINT_TAG_PREFIX,
                '$top' => 500,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('YouTrack API error: ' . $response->body());
        }

        $template = null;
        $templateNumber = -1;
        foreach ($response->json() ?? [] as $tag) {
            if (($tag['name'] ?? null) === $name) {
                return ['id' => $tag['id'], 'name' => $name];
            }
            if (preg_match('/^' . preg_quote(self::ADDED_SPRINT_TAG_PREFIX, '/') . '(\d+)$/', $tag['name'] ?? '', $matches)
                && (int) $matches[1] > $templateNumber) {
                $template = $tag;
                $templateNumber = (int) $matches[1];
            }
        }

        $payload = ['name' => $name];
        foreach (['visibleFor', 'usableFor'] as $sharing) {
            if (is_array($template[$sharing] ?? null)) {
                $payload[$sharing] = $template[$sharing];
            }
        }

        Log::info('Creating YouTrack tag', ['name' => $name]);
        $created = Http::withToken($token)
            ->acceptJson()
            ->post(rtrim($baseUrl, '/') . '/api/issueTags?fields=id,name', $payload);

        if (!$created->successful() || !is_string($created->json('id'))) {
            throw new RuntimeException('YouTrack API error creating tag ' . $name . ': ' . $created->body());
        }

        return ['id' => $created->json('id'), 'name' => $name];
    }

    public function addIssueToSprint(string $agileId, string $sprintId, string $issueDatabaseId): void
    {
        [$baseUrl, $token] = $this->credentials();

        $response = Http::withToken($token)
            ->acceptJson()
            ->post(rtrim($baseUrl, '/') . "/api/agiles/{$agileId}/sprints/{$sprintId}/issues?fields=id", [
                'id' => $issueDatabaseId,
                '$type' => 'Issue',
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('YouTrack API error: ' . $response->body());
        }
    }

    public function uploadAttachment(string $issueId, string $name, string $contents): void
    {
        [$baseUrl, $token] = $this->credentials();

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(120)
            ->attach('file', $contents, $name)
            ->post(rtrim($baseUrl, '/') . '/api/issues/' . rawurlencode($issueId) . '/attachments?fields=id,name');

        if (!$response->successful()) {
            throw new RuntimeException('YouTrack API error uploading ' . $name . ': ' . $response->body());
        }
    }

    public function appendToDescription(string $issueId, string $text): void
    {
        [$baseUrl, $token] = $this->credentials();
        $endpoint = rtrim($baseUrl, '/') . '/api/issues/' . rawurlencode($issueId);

        $current = Http::withToken($token)->acceptJson()->get($endpoint, ['fields' => 'description']);
        if (!$current->successful()) {
            throw new RuntimeException('YouTrack API error: ' . $current->body());
        }

        $description = rtrim((string) ($current->json('description') ?? ''));
        $updated = Http::withToken($token)
            ->acceptJson()
            ->post($endpoint . '?fields=id', ['description' => $description . "\n\n" . $text]);

        if (!$updated->successful()) {
            throw new RuntimeException('YouTrack API error: ' . $updated->body());
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function credentials(): array
    {
        $baseUrl = config('tickets.youtrack_base_url');
        $token = config('tickets.youtrack_token');

        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('YOUTRACK_BASE_URL is not set.');
        }
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('YOUTRACK_TOKEN is not set.');
        }

        return [$baseUrl, $token];
    }

    /**
     * The existing proposal tag with the highest sprint number, e.g. ['id' => '6-123', 'name' => 'proposal151', 'sprint' => 151].
     * Tags are created by the product team; this never creates one.
     */
    public function fetchLatestProposalTag(): ?array
    {
        $baseUrl = config('tickets.youtrack_base_url');
        $token = config('tickets.youtrack_token');

        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('YOUTRACK_BASE_URL is not set.');
        }
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('YOUTRACK_TOKEN is not set.');
        }

        return Cache::remember(
            'youtrack.latest_proposal_tag',
            self::PROPOSAL_CACHE_SECONDS,
            function () use ($baseUrl, $token): ?array {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->get(rtrim($baseUrl, '/') . '/api/issueTags', [
                        'fields' => 'id,name',
                        'query' => 'proposal',
                        '$top' => 500,
                    ]);

                if (!$response->successful()) {
                    throw new RuntimeException('YouTrack API error: ' . $response->body());
                }

                $latest = null;
                foreach ($response->json() ?? [] as $tag) {
                    $name = $tag['name'] ?? null;
                    if (!is_string($name) || !is_string($tag['id'] ?? null)) {
                        continue;
                    }
                    if (!preg_match(self::PROPOSAL_TAG_PATTERN, $name, $matches)) {
                        continue;
                    }

                    $sprint = (int) $matches[1];
                    if ($latest === null || $sprint > $latest['sprint']) {
                        $latest = ['id' => $tag['id'], 'name' => $name, 'sprint' => $sprint];
                    }
                }

                return $latest;
            }
        );
    }

    /**
     * Active values of the project's Priority field, e.g. ["Highest", "High", "Medium"].
     */
    public function fetchPriorityValues(): array
    {
        $baseUrl = config('tickets.youtrack_base_url');
        $token = config('tickets.youtrack_token');
        $projectId = config('tickets.youtrack_project_id');

        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('YOUTRACK_BASE_URL is not set.');
        }
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('YOUTRACK_TOKEN is not set.');
        }
        if (!is_string($projectId) || $projectId === '') {
            throw new RuntimeException('YOUTRACK_PROJECT_ID is not set.');
        }

        return Cache::remember(
            'youtrack.priorities.' . $projectId,
            self::PRIORITY_CACHE_SECONDS,
            function () use ($baseUrl, $token, $projectId): array {
                $endpoint = rtrim($baseUrl, '/') . '/api/admin/projects/' . $projectId . '/customFields';
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->get($endpoint, [
                        'fields' => 'field(name),bundle(values(name,archived,ordinal))',
                    ]);

                if (!$response->successful()) {
                    throw new RuntimeException('YouTrack API error: ' . $response->body());
                }

                foreach ($response->json() ?? [] as $field) {
                    if (($field['field']['name'] ?? null) !== self::PRIORITY_FIELD) {
                        continue;
                    }

                    $values = array_filter(
                        $field['bundle']['values'] ?? [],
                        static fn (array $value): bool => !($value['archived'] ?? false) && is_string($value['name'] ?? null)
                    );
                    usort($values, static fn (array $a, array $b): int => ($a['ordinal'] ?? 0) <=> ($b['ordinal'] ?? 0));

                    return array_values(array_column($values, 'name'));
                }

                return [];
            }
        );
    }

    public function priorityCustomField(string $priority): array
    {
        return [
            '$type' => 'SingleEnumIssueCustomField',
            'name' => self::PRIORITY_FIELD,
            'value' => [
                '$type' => 'EnumBundleElement',
                'name' => $priority,
            ],
        ];
    }

    public function fetchIssueStatus(string $issueId): ?string
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
            ->get($endpoint, [
                'fields' => 'idReadable,customFields(name,value(name))',
            ]);

        if ($response->status() === 404 || $response->status() === 410) {
            return null;
        }

        if (!$response->successful()) {
            throw new RuntimeException('YouTrack API error: ' . $response->body());
        }

        $body = $response->json();
        if (!is_array($body)) {
            throw new RuntimeException('YouTrack response was not valid JSON.');
        }

        $customFields = $body['customFields'] ?? [];
        if (is_array($customFields)) {
            foreach ($customFields as $field) {
                $name = $field['name'] ?? null;
                if ($name === 'State' || $name === 'Status') {
                    $valueName = $field['value']['name'] ?? null;
                    if (is_string($valueName) && $valueName !== '') {
                        return $valueName;
                    }
                }
            }
        }

        return 'Unknown';
    }

    public function createIssue(
        string $type,
        string $summary,
        string $description,
        array $customFields = [],
        array $tags = []
    ): array
    {
        $baseUrl = config('tickets.youtrack_base_url');
        $token = config('tickets.youtrack_token');
        $projectId = config('tickets.youtrack_project_id');

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

        $payload = [
            'project' => ['shortName' => $projectId],
            'summary' => $summary,
            'description' => $description,
            'issuetype' => ['name' => $issueType],
        ];

        if ($customFields !== []) {
            $payload['customFields'] = $customFields;
        }
        if ($tags !== []) {
            $payload['tags'] = $tags;
        }

        Log::info('Creating YouTrack issue', $payload);
        $endpoint = rtrim($baseUrl, '/') . '/api/issues?fields=id,idReadable';
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
            'databaseId' => is_string($body['id'] ?? null) ? $body['id'] : $issueId,
            'url' => $url,
        ];
    }

    public function updateIssueDescription(string $issueId, string $description): void
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
            ->post($endpoint, [
                'description' => $description,
            ]);

        if ($response->status() === 404 || $response->status() === 410) {
            throw new RuntimeException('YouTrack issue not found.');
        }

        if (!$response->successful()) {
            throw new RuntimeException('YouTrack API error: ' . $response->body());
        }
    }
}
