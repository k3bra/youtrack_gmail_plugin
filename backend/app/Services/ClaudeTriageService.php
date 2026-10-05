<?php

namespace App\Services;

use RuntimeException;

/**
 * Runs the /fix-sprint-bugs skill headless (`claude -p`) against the local dev-workspace checkout
 * to triage one YouTrack ticket. Read-only: the tool allowlist cannot write, push or fetch.
 */
class ClaudeTriageService
{
    // Read-only tools only. Ticket content comes from customer emails, so the triage
    // agent must not be able to change anything whatever the ticket text says.
    private const ALLOWED_TOOLS = [
        'Read',
        'Grep',
        'Glob',
        'Bash(git log:*)',
        'Bash(git blame:*)',
        'Bash(git show:*)',
        'Bash(git -C * log *)',
        'Bash(git -C * blame *)',
        'Bash(git -C * show *)',
        'Bash(ls:*)',
    ];

    private const DISALLOWED_TOOLS = ['Write', 'Edit', 'NotebookEdit', 'WebFetch', 'WebSearch', ...ClaudeCli::GIT_WRITE_DENY];

    public function __construct(
        private YouTrackIssueReaderService $reader,
        private TriageResultParser $parser,
        private ClaudeCli $claude
    ) {
    }

    public function triage(string $issueId): array
    {
        $workspace = $this->claude->preflight();
        $ticketFile = 'ai-runs/' . $issueId . '.json';

        $this->writeTicketFile($workspace . '/' . $ticketFile, $this->reader->fetchIssueForTriage($issueId));

        $envelope = $this->claude->run(
            $this->args($issueId, $ticketFile),
            (int) config('tickets.triage_timeout', 600),
            "triage {$issueId}"
        );

        return [
            'analysis' => $this->parser->parse($envelope),
            ...$this->claude->usage($envelope, (string) config('tickets.triage_model')),
        ];
    }

    private function args(string $issueId, string $ticketFile): array
    {
        // No --dry-run: --dry-run forbids continuing, and the Accept step resumes this session.
        // The read-only allowlist is what guarantees triage writes nothing.
        return [
            '-p',
            "/fix-sprint-bugs {$issueId} --ticket-file {$ticketFile} --json",
            '--output-format', 'json',
            '--model', (string) config('tickets.triage_model', 'sonnet'),
            '--max-turns', (string) config('tickets.triage_max_turns', 30),
            '--allowedTools', ...self::ALLOWED_TOOLS,
            '--disallowedTools', ...self::DISALLOWED_TOOLS,
        ];
    }

    private function writeTicketFile(string $path, array $ticket): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Could not create {$dir}.");
        }

        file_put_contents($path, json_encode($ticket, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
