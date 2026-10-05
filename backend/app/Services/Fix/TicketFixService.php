<?php

namespace App\Services\Fix;

use App\Models\TicketFix;
use App\Services\ClaudeCli;
use RuntimeException;

/**
 * Accept & fix: resumes the triage session on Opus to write the fix in a fresh worktree,
 * then commits, pushes feature/<ISSUE>_<slug> and opens a draft PR into the base branch.
 * Claude only edits files; every git and Bitbucket write is done here, after checks.
 */
class TicketFixService
{
    public function __construct(
        private ClaudeCli $claude,
        private GitWorktreeService $git,
        private BitbucketService $bitbucket,
        private FixResultParser $parser
    ) {
    }

    /**
     * @return array<string, mixed> attributes to store on the TicketFix
     */
    public function run(TicketFix $fix): array
    {
        $workspace = $this->claude->preflight();
        $triage = $fix->triage;
        $issueId = $fix->issue_id;
        $repo = self::repoFor($triage->analysis ?? []);
        if ($repo === null) {
            throw new RuntimeException('The triage does not point at exactly one fixable repo.');
        }

        $base = (string) config('tickets.fix_base_branch', 'develop');
        $repoPath = "{$workspace}/{$repo}";
        $branch = 'feature/' . $issueId . '_' . self::slug($this->ticketSummary($workspace, $issueId, $triage->analysis));
        $worktree = "{$workspace}/.ai-fix-worktrees/{$repo}-{$issueId}";
        $attributes = ['repo' => $repo, 'branch' => $branch];
        $pushed = false;

        $this->git->prepare($repoPath, $worktree, $branch, $base);

        try {
            $envelope = $this->claude->run(
                $this->args($issueId, $triage->session_id, $worktree, $branch),
                (int) config('tickets.fix_timeout', 1500),
                "fix {$issueId}"
            );
            $usage = $this->claude->usage($envelope, (string) config('tickets.fix_model'));
            unset($usage['session_id']);
            $attributes += $usage;

            $result = $this->parser->parse($envelope);
            $attributes['result'] = $result;

            if ($result['status'] === 'blocked') {
                return $attributes + [
                    'status' => TicketFix::STATUS_BLOCKED,
                    'error' => $result['blocked_reason'] ?? 'Stopped on a triage gate while implementing.',
                ];
            }

            $changes = $this->git->changes($worktree);
            if ($changes['files'] === []) {
                throw new RuntimeException('Claude reported a fix but changed no files.');
            }
            $maxLines = (int) config('tickets.fix_max_changed_lines', 300);
            if ($changes['changed_lines'] > $maxLines) {
                throw new RuntimeException("The fix changes {$changes['changed_lines']} lines (limit {$maxLines}); not pushed.");
            }
            $attributes['result']['files_committed'] = $changes['files'];

            $attributes['commit_sha'] = $this->git->commit($worktree, $this->commitMessage($issueId, $result['commit_message']));
            $this->git->push($worktree, $branch);
            $pushed = true;

            $attributes['pr_url'] = $this->bitbucket->createDraftPullRequest(
                $this->git->bitbucketSlug($repoPath),
                $branch,
                $base,
                $this->prTitle($issueId, $result['pr_title']),
                $this->prDescription($issueId, $result, $changes['files'])
            );

            return $attributes + ['status' => TicketFix::STATUS_COMPLETED];
        } catch (\Throwable $e) {
            // Keep what we learned (repo, branch, usage, Claude's answer) on the failed record.
            $message = $pushed
                ? "{$branch} was pushed, but the draft PR could not be opened — open it by hand. " . $e->getMessage()
                : $e->getMessage();

            throw new TicketFixFailed($message, $attributes, $e);
        } finally {
            $this->git->remove($repoPath, $worktree);
        }
    }

    /**
     * The single repo the fix goes into, or null when the triage names none, several, or an unknown one.
     */
    public static function repoFor(array $analysis): ?string
    {
        $repos = array_values(array_unique($analysis['repos'] ?? []));

        return count($repos) === 1 && in_array($repos[0], config('tickets.fix_repos', []), true) ? $repos[0] : null;
    }

    /**
     * Same rule as the skill's Step 5: strip [Bracketed] prefixes, non-alphanumerics → _, ≤ 50 chars.
     */
    public static function slug(string $summary): string
    {
        $slug = preg_replace('/\[[^\]]*\][ -]*/', '', $summary);
        $slug = trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', (string) $slug), '_');

        return rtrim(substr($slug, 0, 50), '_') ?: 'fix';
    }

    private function args(string $issueId, string $sessionId, string $worktree, string $branch): array
    {
        // Rule paths starting with // are absolute. Edits are only allowed inside the worktree.
        return [
            '-p',
            "continue with {$issueId} --worktree {$worktree} --branch {$branch} --json",
            '--resume', $sessionId,
            '--output-format', 'json',
            '--model', (string) config('tickets.fix_model', 'opus'),
            '--max-turns', (string) config('tickets.fix_max_turns', 60),
            '--allowedTools',
            'Read', 'Grep', 'Glob',
            "Edit(/{$worktree}/**)", "Write(/{$worktree}/**)",
            'Bash(php -l *)',
            "Bash(git -C {$worktree} diff*)", "Bash(git -C {$worktree} status*)",
            'Bash(git log:*)', 'Bash(git blame:*)', 'Bash(git show:*)',
            'Bash(git -C * log *)', 'Bash(git -C * blame *)', 'Bash(git -C * show *)',
            'Bash(ls:*)',
            '--disallowedTools', 'NotebookEdit', 'WebFetch', 'WebSearch', ...ClaudeCli::GIT_WRITE_DENY,
        ];
    }

    private function ticketSummary(string $workspace, string $issueId, ?array $analysis): string
    {
        $file = "{$workspace}/ai-runs/{$issueId}.json";
        $ticket = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return (string) ($ticket['summary'] ?? $analysis['summary'] ?? 'fix');
    }

    private function commitMessage(string $issueId, string $message): string
    {
        $subject = trim(strtok($message, "\n"));
        if (!str_starts_with($subject, $issueId)) {
            $subject = "{$issueId} {$subject}";
        }

        return $subject . "\n\nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>";
    }

    private function prTitle(string $issueId, string $title): string
    {
        $title = trim($title);

        return str_starts_with($title, $issueId) ? $title : "{$issueId}: {$title}";
    }

    private function prDescription(string $issueId, array $result, array $files): string
    {
        $issueUrl = rtrim((string) config('tickets.youtrack_base_url'), '/') . "/issue/{$issueId}";

        return "**Ticket:** [{$issueId}]({$issueUrl})\n\n"
            . trim($result['pr_description']) . "\n\n"
            . '**Files:** ' . implode(', ', array_map(fn ($f) => "`{$f}`", $files)) . "\n\n"
            . "---\n"
            . "🤖 Draft PR generated by **Accept & fix** in the YouTrack Gmail plugin (`/fix-sprint-bugs`). "
            . "A human must review, test and mark it ready — do not merge as-is.\n\n"
            . '🤖 Generated with [Claude Code](https://claude.com/claude-code)';
    }
}
